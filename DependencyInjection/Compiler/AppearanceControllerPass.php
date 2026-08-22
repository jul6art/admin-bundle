<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Controller\AppearanceController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the appearance screen when there is no entity manager to save through.
 *
 * `class_exists(EntityManagerInterface::class)` is not the right question and the extension asks it
 * only as a cheap first filter: a project can perfectly well have `doctrine/orm` in its tree
 * without `DoctrineBundle` registered — a dependency of a dependency, a console-only tool — and in
 * that case the class is there while the *service* is not. Autowiring then fails the whole build
 * with "no service for EntityManagerInterface", pointing at a controller the project never asked
 * for.
 *
 * An extension cannot make this call: it runs before the other bundles have configured anything,
 * so `$container->has()` there always answers false. Hence a pass.
 */
final class AppearanceControllerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AppearanceController::class)) {
            return;
        }

        if ($container->has(EntityManagerInterface::class) || $container->has('doctrine.orm.entity_manager')) {
            return;
        }

        $container->removeDefinition(AppearanceController::class);
    }
}
