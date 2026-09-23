<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Search\GlobalSearch;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the header search engine when there is no entity manager to query through.
 *
 * Same question, same answer as `AppearanceControllerPass`: `doctrine/orm` in the tree is not
 * `DoctrineBundle` registered, and only a pass can tell — the extension runs before the other bundles
 * have configured anything.
 */
final class GlobalSearchPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(GlobalSearch::class)) {
            return;
        }

        if ($container->has(EntityManagerInterface::class) || $container->has('doctrine.orm.entity_manager')) {
            return;
        }

        $container->removeDefinition(GlobalSearch::class);
    }
}
