<?php

declare(strict_types=1);

/*
 * Copyright (c) the Contributors as noted in the AUTHORS file.
 *
 * This file is part of the Park-Manager project.
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace ParkManager\Core\Infrastructure\DependencyInjection\Module\Traits;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\GlobFileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\{
    ClosureLoader,
    DirectoryLoader,
    IniFileLoader,
    PhpFileLoader,
    XmlFileLoader,
    YamlFileLoader
};

/**
 * @author Sebastiaan Stok <s.stok@rollerworks.net>
 */
trait ServiceLoaderTrait
{
    protected function getServiceLoader(ContainerBuilder $container, $servicesPath): DelegatingLoader
    {
        $locator = new FileLocator($servicesPath);
        $resolver = new LoaderResolver([
            new PhpFileLoader($container, $locator),
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new GlobFileLoader($locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        return new DelegatingLoader($resolver);
    }
}
