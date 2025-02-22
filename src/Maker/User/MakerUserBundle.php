<?php
/**
 * Copyright 2025 (C) IDMarinas - All Rights Reserved
 *
 * Last modified by "IDMarinas" on 20/02/2025, 14:32
 *
 * @project IDMarinas Maker Bundle
 * @see     https://github.com/idmarinas/maker-bundle
 *
 * @file    MakerUserBundle.php
 * @date    30/01/2025
 * @time    20:11
 *
 * @author  Iván Diaz Marinas (IDMarinas)
 * @license BSD 3-Clause License
 *
 * @since   2.0.0
 */

namespace Idm\Bundle\Maker\Maker\User;

use Exception;
use Idm\Bundle\Maker\Maker\User\MakerUserBundle\SecurityTrait;
use Idm\Bundle\Maker\Traits\Maker\ArrayUtilsTrait;
use Idm\Bundle\Maker\Traits\Maker\GenerateClassTrait;
use Idm\Bundle\Maker\Traits\Maker\MakeHelpFileTrait;
use Idm\Bundle\User\IdmUserBundle;
use Idm\Bundle\User\Model\Entity\AbstractConnections;
use Idm\Bundle\User\Model\Entity\AbstractPremium;
use Idm\Bundle\User\Model\Entity\AbstractUser;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class MakerUserBundle extends AbstractMaker
{
	use ArrayUtilsTrait;
	use MakeHelpFileTrait;
	use GenerateClassTrait;
	use SecurityTrait;

	public function __construct (private readonly FileManager $fileManager) {}

	/**
	 * @inheritDoc
	 */
	public static function getCommandName (): string
	{
		return 'make:idm:user:bundle';
	}

	public static function getCommandDescription (): string
	{
		return 'Create the User Bundle related files';
	}

	/**
	 * @inheritDoc
	 */
	public function configureCommand (Command $command, InputConfiguration $inputConfig): void
	{
		$command->setHelp($this->getMakeHelpFileContents('MakeUserBundleFile.txt'));
	}

	/**
	 * @inheritDoc
	 */
	public function configureDependencies (DependencyBuilder $dependencies): void
	{
		$dependencies->addClassDependency(IdmUserBundle::class, 'idmarinas/user-bundle');
		$dependencies->addClassDependency(SecurityBundle::class, 'security');

		if (!class_exists(AbstractPremium::class)) {
			throw new RuntimeCommandException(
				'Please run "composer upgrade idmarinas/user-bundle". Version 2.0 or greater of this bundle is required.'
			);
		}
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function generate (InputInterface $input, ConsoleStyle $io, Generator $generator): void
	{
		self::$generator = $generator;

		// Sources
		$sources = include __DIR__ . '/MakerUserBundle/sources.php';
		$sources = $sources($generator);

		$this->generateClasses($sources);

		// Config files
		$this->tplConfigRateLimiterYaml();
		$this->tplConfigResetPasswordYaml($sources['ResetPasswordRequestRepository']['class']->getFullName());
		$this->configSecurityYaml($sources['User']['class']);
		$this->configDoctrineYaml($sources);

		$generator->writeChanges();

		$this->writeSuccessMessage($io);
	}

	protected static function getTpl (string $file): string
	{
		return Path::canonicalize(dirname(__DIR__, 3) . '/templates/user/bundle/' . $file);
	}

	/** @internal */
	private function configDoctrineYaml (array $sources): void
	{
		$doctrineYaml = 'config/packages/doctrine.yaml';

		$manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($doctrineYaml));
		$data = $manipulator->getData();

		$data = self::arrayMergeRecursive($data, [
			'doctrine' => [
				'orm' => [
					'resolve_target_entities' => [
						AbstractUser::class        => $sources['User']['class']->getFullName(),
						AbstractPremium::class     => $sources['Premium']['class']->getFullName(),
						AbstractConnections::class => $sources['Connections']['class']->getFullName(),
					],
				],
			],
		]);

		$manipulator->setData($data);

		self::$generator->dumpFile($doctrineYaml, $manipulator->getContents());
	}

	/** @internal */
	private function tplConfigRateLimiterYaml (): void
	{
		$rateLimiter = 'config/packages/rate_limiter.yaml';
		$tplRateLimiter = 'config/packages/rate_limiter.tpl.yaml';

		$tplManipulator = new YamlSourceManipulator($this->fileManager->getFileContents(self::getTpl($tplRateLimiter)));

		if (!$this->fileManager->fileExists($rateLimiter)) {
			self::$generator->dumpFile($rateLimiter, $tplManipulator->getContents());

			return;
		}

		$manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($rateLimiter));

		$tplData = $tplManipulator->getData();
		$data = $manipulator->getData();

		$tplConfig = $tplData['framework']['rate_limiter'];
		$config = &$data['framework']['rate_limiter'];

		$config['idm_user.login.username_ip.main'] ??= $tplConfig['idm_user.login.username_ip.main'];
		$config['idm_user.login.ip.main'] ??= $tplConfig['idm_user.login.ip.main'];

		$config['idm_user.login.ip.main']['_'] = $manipulator->createEmptyLine();

		$config['idm_user.login.username_ip.admin'] ??= $tplConfig['idm_user.login.username_ip.admin'];
		$config['idm_user.login.ip.admin'] ??= $tplConfig['idm_user.login.ip.admin'];

		$services = &$data['services'];

		if (isset($services['idm_user.rate_limiter.login.main']['synthetic'])) {
			$services['idm_user.rate_limiter.login.main'] = null;
		}

		if (isset($services['idm_user.rate_limiter.login.admin']['synthetic'])) {
			$services['idm_user.rate_limiter.login.admin'] = null;
		}

		$services['idm_user.rate_limiter.login.main'] ??= $tplData['services']['idm_user.rate_limiter.login.main'];
		$services['idm_user.rate_limiter.login.main']['_'] = $manipulator->createEmptyLine();

		$services['idm_user.rate_limiter.login.admin'] ??= $tplData['services']['idm_user.rate_limiter.login.admin'];

		$manipulator->setData($data);

		self::$generator->dumpFile($rateLimiter, $manipulator->getContents());
	}

	/** @internal */
	private function tplConfigResetPasswordYaml (string $repositoryClassFullName): void
	{
		$resetPassword = 'config/packages/reset_password.yaml';

		$tplData = [
			'symfonycasts_reset_password' => [
				'request_password_repository' => $repositoryClassFullName,
			],
		];

		if (!$this->fileManager->fileExists($resetPassword)) {
			self::$generator->dumpFile($resetPassword, Yaml::dump($tplData));

			return;
		}

		$manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($resetPassword));

		$data = $manipulator->getData();
		$config = &$data['symfonycasts_reset_password'];

		if (!empty($config['request_password_repository'])) {
			$config['request_password_repository'] = null;
		}

		$config['request_password_repository'] ??= $repositoryClassFullName;

		$manipulator->setData($data);

		self::$generator->dumpFile($resetPassword, $manipulator->getContents());
	}

	/** @internal */
	private function configSecurityYaml (ClassNameDetails $classNameDetails): void
	{
		$securityYaml = 'config/packages/security.yaml';

		if (!$this->fileManager->fileExists($securityYaml)) {
			self::$generator->dumpFile($securityYaml, Yaml::dump(['security' => []]));
		}

		$manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($securityYaml));
		$data = $manipulator->getData();

		$data = $this->updateSecurityConfig($data, $classNameDetails);

		$manipulator->setData($data);

		self::$generator->dumpFile($securityYaml, $manipulator->getContents());
	}
}
