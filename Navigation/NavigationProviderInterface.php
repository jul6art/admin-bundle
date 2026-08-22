<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

/**
 * Where the sidebar's contents come from.
 *
 * One implementation per module rather than one big list: a module that is removed takes its menu
 * with it, and a module that is added does not require editing a file it does not own.
 *
 * ```php
 * #[AutoconfigureTag('admin.navigation')]
 * final class UserNavigation implements NavigationProviderInterface
 * {
 *     public function sections(): iterable
 *     {
 *         yield new NavSection('admin.platform', 'nav.platform', 'fa-solid fa-shield-halved', [
 *             new NavItem('admin_dashboard', 'nav.dashboard', 'fa-solid fa-house'),
 *             new NavItem('admin_user_index', 'nav.users', 'fa-solid fa-users', permission: 'user:read'),
 *         ], priority: 100);
 *     }
 * }
 * ```
 *
 * Services are collected by the `admin.navigation` tag, which `AdminBundle` autoconfigures for
 * every implementation — so an application only has to make the class a service.
 */
interface NavigationProviderInterface
{
    /**
     * @return iterable<NavSection>
     */
    public function sections(): iterable;
}
