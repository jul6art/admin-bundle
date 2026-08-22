<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Jul6Art\AdminBundle\Navigation\NavItem;
use Jul6Art\AdminBundle\Navigation\NavSection;

/**
 * What a module writes: its own slice of the menu, with the gates next to the links.
 */
final class WidgetNavigation implements NavigationProviderInterface
{
    #[\Override]
    public function sections(): iterable
    {
        yield new NavSection('widgets', 'nav.widgets', 'fa-solid fa-cube', [
            new NavItem('admin_widget_index', 'nav.widget_list', 'fa-solid fa-list'),
            new NavItem('admin_widget_show', 'nav.widget_secret', 'fa-solid fa-lock', permission: 'widget:secret', routeParameters: ['id' => 1]),
            new NavItem('admin_report_index', 'nav.widget_reports', 'fa-solid fa-chart-pie', feature: 'reporting'),
        ], priority: 10);

        // Une section entièrement gardée : refusée, elle disparaît avec ses entrées, et sans coûter
        // une vérification de permission par entrée.
        yield new NavSection('secret', 'nav.secret', 'fa-solid fa-eye-slash', [
            new NavItem('admin_dashboard', 'nav.secret_home', 'fa-solid fa-house'),
        ], permission: 'section:secret', priority: 99);
    }
}
