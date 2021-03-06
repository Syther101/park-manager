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

namespace ParkManager\Bundle\ServiceBusPolicyGuardBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\Expression;
use function count;
use function implode;
use function is_string;
use function mb_strpos;
use function preg_match;
use function preg_quote;
use function preg_split;
use function sprintf;
use function trim;

final class PolicyGuardConfigurationPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private const NAME_REGEX = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('park_manager.service_bus.policy_guard', true) as $serviceId => $tags) {
            $busId = $tags[0]['bus-id'];

            $guardServiceDef = $container->getDefinition($serviceId);
            $guardServiceDef->setArgument(1, self::getPoliciesListByType($container, $busId . '.policy_guard.ns_policy'));
            $guardServiceDef->setArgument(2, self::getPoliciesListByType($container, $busId . '.policy_guard.class_policy'));
            $guardServiceDef->setArgument(3, self::processRegexpPolicies($container, $busId, $policyMaps));
            $guardServiceDef->setArgument(4, $policyMaps);
            $guardServiceDef->setArgument(5, self::getExpressionVariablesHolder($container, $busId));

            $container->findDefinition($guardServiceDef->getArgument(0))
                ->setArgument(1, $this->findAndSortTaggedServices($busId . '.policy_guard.expression_language_provider', $container));
        }
    }

    /**
     * @return Definition[]
     */
    private static function getPoliciesListByType(ContainerBuilder $container, string $tagName): array
    {
        $processedPolicies = [];

        foreach ($container->findTaggedServiceIds($tagName) as $configService => $tags) {
            [$pattern, $policy] = $container->getDefinition($configService)->getArguments();
            $container->removeDefinition($configService);

            // Policy was unset
            if ($policy === null) {
                continue;
            }

            if (is_string($policy)) {
                $policy = new Definition(Expression::class, [$policy]);
            }

            foreach (self::expendReferencePattern($pattern) as $processedPattern) {
                $processedPolicies[$processedPattern] = $policy;
            }
        }

        return $processedPolicies;
    }

    private static function processRegexpPolicies(ContainerBuilder $container, string $busName, &$processedPolicies): string
    {
        $policiesPerPrefix = [];
        $processedPolicies = [];

        $mark = 0;

        foreach ($container->findTaggedServiceIds($busName . '.policy_guard.regexp_policy') as $configService => $tags) {
            [$regexp, $policy] = $container->getDefinition($configService)->getArguments();
            $container->removeDefinition($configService);

            if ($policy === null) {
                continue;
            }

            if (is_string($policy)) {
                $policy = new Definition(Expression::class, [$policy]);
            }

            $policiesPerPrefix[$tags[0]['prefix']][++$mark] = $regexp;
            $processedPolicies[$mark]                       = $policy;
        }

        if (! count($policiesPerPrefix)) {
            return '{^/$}';
        }

        return self::generatePolicyPatternGroups($policiesPerPrefix);
    }

    /**
     * @param array[] $policiesPerPrefix prefix => [pattern-index => sub-pattern]
     */
    private static function generatePolicyPatternGroups(array $policiesPerPrefix): string
    {
        $groups = [];

        foreach ($policiesPerPrefix as $prefix => $patterns) {
            $prefixRegexp = preg_quote((string) $prefix, '') . '(?';

            foreach ($patterns as $idx => $pattern) {
                $prefixRegexp .= '|' . $pattern . '(*:' . $idx . ')';
            }

            $prefixRegexp .= ')';

            $groups[] = $prefixRegexp;
        }

        return '{^' . implode('|', $groups) . '$}su';
    }

    /**
     * @return string[]|ServiceClosureArgument[]
     */
    private static function getExpressionVariablesHolder(ContainerBuilder $container, string $busId): array
    {
        $variables = [];
        $services  = [];

        foreach ($container->findTaggedServiceIds($busId . '.policy_guard.variable') as $configService => $tags) {
            list($name, $value) = $container->getDefinition($configService)->getArguments();
            $container->removeDefinition($configService);

            if ($value instanceof Reference) {
                $services[$name] = new ServiceClosureArgument($value);
            } else {
                $variables[$name] = $container->getParameterBag()->resolveValue($value);
            }
        }

        $variables['services'] = (new Definition(ServiceLocator::class))
            ->addTag('container.service_locator')
            ->addArgument($services)
            ->setPublic(false);

        return $variables;
    }

    private static function expendReferencePattern(string $str): array
    {
        $strN = trim($str, '\\');

        if (mb_strpos($strN, '{') === false) {
            if (! preg_match('/^(?:' . self::NAME_REGEX . '\\\\?)+$/s', $strN)) {
                throw new \InvalidArgumentException(sprintf('Policy "%s" contains invalid characters.', $str));
            }

            return [$strN];
        }

        if (! preg_match('/^(?P<start>(?:' . self::NAME_REGEX . '\\\\)+)\{(?P<expend>' . self::NAME_REGEX . '(?:\s*,\s*' . self::NAME_REGEX . ')*)\}(?P<remainder>(?:\\\\' . self::NAME_REGEX . ')*)$/s', $strN, $m)) {
            throw new \InvalidArgumentException(sprintf('Policy "%s" contains invalid characters.', $str));
        }

        $namespaces = [];

        foreach (preg_split('/\h*,\h*/', $m['expend']) as $part) {
            $namespaces[] = $m['start'] . $part . $m['remainder'];
        }

        return $namespaces;
    }
}
