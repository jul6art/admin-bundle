<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Jul6Art\AdminBundle\Navigation\NavItem;
use Jul6Art\AdminBundle\Navigation\NavSection;

/**
 * Deux sections, chacune dans SON espace connecté — la forme qu'a une application qui en a deux.
 *
 * Les deux portent la même permission et le même libellé à dessein : c'est le cas où le défaut se
 * voyait, deux entrées indiscernables côte à côte dont l'une quitte l'espace.
 */
final class SpacedNavigation implements NavigationProviderInterface
{
    #[\Override]
    public function sections(): iterable
    {
        yield new NavSection('customer', 'nav.dashboard', 'fa-solid fa-house', [
            new NavItem('customer_dashboard', 'nav.dashboard', 'fa-solid fa-house', permission: 'widget:list'),
        ], space: 'customer');

        yield new NavSection('platform', 'nav.dashboard', 'fa-solid fa-house', [
            new NavItem('platform_dashboard', 'nav.dashboard', 'fa-solid fa-house', permission: 'widget:list'),
        ], space: 'platform');
    }
}
