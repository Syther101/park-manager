<?php

declare(strict_types=1);

/*
 * This file is part of the Park-Manager project.
 *
 * Copyright (c) the Contributors as noted in the AUTHORS file.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParkManager\Component\Module;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Marker interface for the DoctrineDbalTypesConfiguratorTrait.
 */
interface RegistersDoctrineDbalTypes
{
    public function registerDoctrineDbalTypes(ContainerBuilder $container, string $moduleDirectory): void;
}
