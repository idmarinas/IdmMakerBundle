<?php
/**
 * Copyright 2025 (C) IDMarinas - All Rights Reserved
 *
 * Last modified by "IDMarinas" on 01/02/2025, 12:38
 *
 * @project IDMarinas Maker Bundle
 * @see     https://github.com/idmarinas/maker-bundle
 *
 * @file    MakerUserBundleFiles.php
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
use Idm\Bundle\Maker\Maker\User\MakerUserBundleFiles\SecurityTrait;
use Idm\Bundle\Maker\Traits\MakeHelpFileTrait;
use Idm\Bundle\User\IdmUserBundle;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;

final class MakerUserBundleFiles extends AbstractMaker
{
	use MakeHelpFileTrait;
	use SecurityTrait;

	public function __construct (private readonly FileManager $fileManager) {}

	/**
	 * @inheritDoc
	 */
	public static function getCommandName (): string
	{
		return 'make:idm:user-bundle';
	}

	public static function getCommandDescription (): string
	{
		return 'Create de User Bundle related files';
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
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function generate (InputInterface $input, ConsoleStyle $io, Generator $generator): void
	{
		/* @var array<string, ClassNameDetails> $generatedClasses */
		$generatedClasses = [];

		// Sources
		$sources = [
			// Entities
			'Connections'                    => 'Entity\\User',
			'ConnectionsLog'                 => 'Entity\\User',
			'Log'                            => 'Entity\\User',
			'Premium'                        => 'Entity\\User',
			'PremiumLog'                     => 'Entity\\User',
			'ResetPasswordRequest'           => 'Entity\\User',
			'ResetPasswordRequestLog'        => 'Entity\\User',
			'User'                           => 'Entity\\User',
			// Repositories
			'UserRepository'                 => 'Repository\\User',
			'ResetPasswordRequestRepository' => 'Repository\\User',
			// Admin Crud Controller
			'UserCrudController'             => 'Controller\\Admin\\User',
		];

		foreach ($sources as $name => $namespace) {
			$class = $generator->createClassNameDetails($name, $namespace);
			$generatedClasses[$name] = $class;

			$generator->generateClass(
				$class->getFullName(),
				self::tplBundle(
					sprintf('src\\%s\\%s.tpl.php', $namespace, $name)
				)
			);
		}

		// Config files
		$this->tplConfigRateLimiterYaml();
		$this->tplConfigResetPasswordYaml($generatedClasses['ResetPasswordRequestRepository']->getFullName());
		$this->configSecurityYaml($generatedClasses['User']);

		$generator->generateFile('config/routes/idm_user.yaml', self::tplBundle('config/routes/idm_user.tpl.yaml'));

		$generator->writeChanges();

		$this->writeSuccessMessage($io);
	}

	/** @internal */
	private function tplConfigRateLimiterYaml (): void
	{
		$rateLimiter = 'config/packages/rate_limiter.yaml';
		$tplRateLimiter = 'config/packages/rate_limiter.tpl.yaml';

		$tplManipulator = new YamlSourceManipulator($this->fileManager->getFileContents(self::tplBundle($tplRateLimiter)));

		if (!$this->fileManager->fileExists($rateLimiter)) {
			$this->fileManager->dumpFile($rateLimiter, $tplManipulator->getContents());

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

		$this->fileManager->dumpFile($rateLimiter, $manipulator->getContents());
	}

	private function tplConfigResetPasswordYaml (string $repositoryClassFullName): void
	{
		$resetPassword = 'config/packages/reset_password.yaml';

		$tplData = [
			'symfonycasts_reset_password' => [
				'request_password_repository' => $repositoryClassFullName,
			],
		];

		if (!$this->fileManager->fileExists($resetPassword)) {
			$this->fileManager->dumpFile($resetPassword, Yaml::dump($tplData));

			return;
		}

		$manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($resetPassword));

		$data = $manipulator->getData();
		$config = &$data['symfonycasts_reset_password'];

		if (isset($config['request_password_repository']) && '' == $config['request_password_repository']) {
			$config['request_password_repository'] = null;
		}

		$config['request_password_repository'] ??= $repositoryClassFullName;

		$manipulator->setData($data);

		$this->fileManager->dumpFile($resetPassword, $manipulator->getContents());
	}

	private function configSecurityYaml (ClassNameDetails $classNameDetails): void
	{
		$securityYaml = 'config/packages/security.yaml';

		if (!$this->fileManager->fileExists($securityYaml)) {
			$this->fileManager->dumpFile($securityYaml, Yaml::dump(['security' => []]));
		}

		$manipulator = new YamlSourceManipulator($securityYaml);
		$data = $manipulator->getData();

		$data = $this->updateSecurityConfig($data, $classNameDetails);

		$manipulator->setData($data);

		$this->fileManager->dumpFile($securityYaml, $manipulator->getContents());
	}

	/** @internal */
	private function tplBundle (string $file): string
	{
		return 'templates/user/bundle/' . $file;
	}
}
