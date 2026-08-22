<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Jul6Art\AdminBundle\Navigation\FeatureVisibilityInterface;
use Jul6Art\AdminBundle\Navigation\NavigationBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the navigation's feature checker when the application registered none.
 *
 * This is a **service** question, not a class one — the interface always exists, the bundle ships
 * it; what is uncertain is whether anything implements it. An extension cannot tell: it runs
 * before the other bundles have configured anything, so `$container->has()` there always answers
 * false. Hence a pass.
 *
 * Leaving the argument in place would fail the build with "no service implements
 * FeatureVisibilityInterface" on every application that has no feature system — which is most of
 * them.
 */
final class FeatureVisibilityPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(NavigationBuilder::class)) {
            return;
        }

        if ($container->has(FeatureVisibilityInterface::class)) {
            return;
        }

        $container->getDefinition(NavigationBuilder::class)->setArgument('$features', null);
    }
}
