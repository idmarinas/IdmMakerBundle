<?php
/**
 * Copyright 2025 (C) IDMarinas - All Rights Reserved
 *
 * Last modified by "IDMarinas" on 11/02/2025, 15:46
 *
 * @project IDMarinas Maker Bundle
 * @see     https://github.com/idmarinas/maker-bundle
 *
 * @file    maker.php
 * @date    30/01/2025
 * @time    19:01
 *
 * @author  Iván Diaz Marinas (IDMarinas)
 * @license BSD 3-Clause License
 *
 * @since   2.0.0
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Idm\Bundle\Maker\IdmMakerBundle;
use ReflectionClass;

return static function (ContainerConfigurator $container) {
	$container->extension('maker', [
		'root_namespace' => (new ReflectionClass(IdmMakerBundle::class))->getNamespaceName(),
	]);
};
