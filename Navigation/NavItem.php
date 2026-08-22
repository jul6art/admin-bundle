<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

/**
 * One link in the sidebar.
 *
 * The gate is declared **here**, next to the link, and not repeated in the template. That is the
 * whole point of describing navigation in PHP: a menu entry whose guard drifts from its route's
 * guard produces a visible link that answers 403 — an interface bug that no controller test sees,
 * because the controller is right.
 */
final readonly class NavItem
{
    /**
     * @param string               $route           the route name; also the default active-state prefix
     * @param string               $labelKey        translation key
     * @param string               $icon            CSS classes of the icon, e.g. `fa-solid fa-users`
     * @param string               $labelDomain     translation domain of `$labelKey`
     * @param string|null          $permission      an attribute passed to `isGranted()` — a permission
     *                                              code, a role, anything a voter answers. `null`
     *                                              means visible to any authenticated account.
     * @param string|null          $feature         a feature code, checked through
     *                                              {@see FeatureVisibilityInterface}. `null` means
     *                                              no feature gate. With no checker registered, a
     *                                              gated item is **hidden** — a paid module must
     *                                              not appear for free because a service is missing.
     * @param array<string, mixed> $routeParameters
     * @param string|null          $activePrefix    route-name prefix marking this item active.
     *                                              Defaults to `$route` minus a trailing `_index`,
     *                                              so a listing stays highlighted on its own
     *                                              show/edit pages.
     */
    public function __construct(
        public string $route,
        public string $labelKey,
        public string $icon,
        public string $labelDomain = 'messages',
        public ?string $permission = null,
        public ?string $feature = null,
        public array $routeParameters = [],
        public ?string $activePrefix = null,
    ) {
    }

    public function activePrefix(): string
    {
        return $this->activePrefix ?? preg_replace('/_index$/', '', $this->route) ?? $this->route;
    }
}
