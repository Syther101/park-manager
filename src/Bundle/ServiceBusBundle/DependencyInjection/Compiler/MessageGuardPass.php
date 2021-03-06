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

namespace ParkManager\Bundle\ServiceBusBundle\DependencyInjection\Compiler;

use ParkManager\Bundle\ServiceBusBundle\Guard\EventListener\UnauthorizedExceptionListener;
use ParkManager\Component\ServiceBus\MessageGuard\PermissionGuard;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function array_merge;
use function is_a;
use function krsort;
use function sprintf;

/**
 * The MessageGuardPass registers the PermissionGuard's for MessageBuses.
 *
 * Caution: Be sure to register this *before* the RegisterListenersPass.
 */
final class MessageGuardPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('park_manager.service_bus', true) as $busId => $tags) {
            if (! $container->has($busId . '.middleware.message_guard')) {
                $container->log($this, sprintf('MessageGuardMiddleware is not enabled for %s, ignoring.', $busId));

                continue;
            }

            $guardService = $container->findDefinition($busId . '.middleware.message_guard');
            $guardService->setArgument(0, new IteratorArgument(
                $this->processGuards($container, $container->findTaggedServiceIds($busId . '.message_guard', true))
            ));
        }

        $container->register(UnauthorizedExceptionListener::class, UnauthorizedExceptionListener::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.hot_path'); // Inline for better performance.
    }

    /**
     * @param array[] $collectedServices
     */
    private function processGuards(ContainerBuilder $container, array $collectedServices): array
    {
        $services = [];

        foreach ($collectedServices as $serviceId => $attributes) {
            $class = $container->getParameterBag()->resolveValue(
                $container->getDefinition((string) $serviceId)->getClass()
            );

            if (! is_a($class, PermissionGuard::class, true)) {
                throw new \LogicException(sprintf('%s must implement the %s when used as a message-guard.', $class, PermissionGuard::class));
            }

            $services[$attributes[0]['priority'] ?? 0][] = new Reference($serviceId);
        }

        if ($services) {
            krsort($services);
            $services = array_merge(...$services);
        }

        return $services;
    }
}
