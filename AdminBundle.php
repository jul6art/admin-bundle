<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle;

use Jul6Art\AdminBundle\DependencyInjection\Compiler\AppearanceControllerPass;
use Jul6Art\AdminBundle\DependencyInjection\Compiler\FeatureVisibilityPass;
use Jul6Art\AdminBundle\DependencyInjection\Compiler\PerformanceControllerPass;
use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The back-office shell: a layout, a theme, per-user appearance, the sign-in pages, and a
 * navigation contract the application fills.
 */
class AdminBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Toute implémentation du contrat devient une source de menu sans que le projet ait à
        // poser un tag : déclarer la classe en service suffit. Une entrée de menu absente parce
        // qu'un tag manquait est exactement le genre de défaut qu'on ne voit qu'à l'usage.
        $container->registerForAutoconfiguration(NavigationProviderInterface::class)
            ->addTag('admin.navigation');

        $container->addCompilerPass(new FeatureVisibilityPass());
        // ⚠️ La priorité n'est pas décorative. `RegisterControllerArgumentLocatorsPass` de
        // FrameworkBundle tourne dans la même phase et, à priorité égale, avant le nôtre puisque
        // son bundle est enregistré en premier : il aurait déjà construit le locator d'arguments
        // du contrôleur, et le retirer ensuite fait échouer la compilation sur un service
        // « inexistant » que plus personne ne réclame explicitement.
        $container->addCompilerPass(new AppearanceControllerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        // Même priorité, même raison : le locataire de service survit à un `removeDefinition`
        // posé plus tard, et l'écran de profilage dépend d'un bundle que celui-ci ne requiert pas.
        $container->addCompilerPass(new PerformanceControllerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
