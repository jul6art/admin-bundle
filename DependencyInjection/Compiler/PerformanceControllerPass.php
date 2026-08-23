<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Jul6Art\AdminBundle\Controller\PerformanceController;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Retire l'écran du profileur quand `jul6art/core-bundle` n'est pas là pour l'alimenter.
 *
 * `admin-bundle` ne requiert PAS le core : une application peut prendre la coquille pour ses
 * pages d'authentification et son thème sans vouloir la brique de profilage. Le contrôleur type
 * pourtant `PerformanceStoreInterface` ; laissé dans le conteneur, il ferait échouer la
 * compilation sur une référence introuvable, en désignant un écran que le projet n'a jamais
 * demandé.
 *
 * ⚠️ `class_exists()` ne suffirait pas : le core peut être dans l'arbre de dépendances sans être
 * enregistré comme bundle (dépendance d'une dépendance), et le service n'existe alors pas. C'est
 * la question qu'une extension ne peut pas poser — elle s'exécute avant que les autres bundles
 * aient configuré quoi que ce soit — d'où ce pass.
 */
final class PerformanceControllerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PerformanceController::class)) {
            return;
        }

        if ($container->has(PerformanceStoreInterface::class)) {
            return;
        }

        $container->removeDefinition(PerformanceController::class);
    }
}
