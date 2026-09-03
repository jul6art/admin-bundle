<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Collects every provider's sections, drops what the signed-in account may not see, and orders
 * what is left.
 *
 * ## What "may not see" means, in order
 *
 * 1. a **section** whose permission or feature is refused disappears with all its items — checking
 *    the section first also saves N permission checks;
 * 2. an **item** whose permission or feature is refused disappears;
 * 3. a section left with no visible item disappears. A group header that opens onto nothing is
 *    worse than no group at all: it advertises a module the account cannot reach.
 *
 * ## Why the permission goes through the authorization checker
 *
 * `isGranted()` accepts anything a voter answers — a role, a permission code, an entity attribute.
 * The bundle therefore needs to know nothing about how the application expresses authorisation,
 * and an application changing its ACL engine changes nothing here.
 *
 * ⚠️ **A permission code that no voter recognises is granted, not refused.** Symfony's access
 * decision manager returns `true` when every voter abstains, under the default strategy. That is a
 * property of Symfony, not of this class — but it means a typo in a `NavItem`'s permission makes a
 * link *appear*, and the route behind it will then refuse. Cover the menu with a test that walks it
 * rather than trusting the string.
 */
final readonly class NavigationBuilder
{
    /**
     * @param iterable<NavigationProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ?FeatureVisibilityInterface $features = null,
    ) {
    }

    /**
     * @return list<NavSection>
     */
    /**
     * @param string|null $space l'espace connecté à servir, ou `null` pour ne rien filtrer
     *
     * @return list<NavSection>
     */
    public function build(?string $space = null): array
    {
        $sections = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->sections() as $section) {
                if (!self::servesSpace($section, $space)) {
                    continue;
                }

                if (!$this->isVisible($section->permission, $section->feature)) {
                    continue;
                }

                $items = array_values(array_filter(
                    $section->items,
                    fn (NavItem $item): bool => $this->isVisible($item->permission, $item->feature),
                ));

                if ([] === $items) {
                    continue;
                }

                $sections[] = $section->withItems($items);
            }
        }

        // `usort` n'est pas stable avant PHP 8.0 et l'est depuis : deux sections de même priorité
        // gardent donc l'ordre de déclaration, ce qui est l'ordre que le lecteur du provider voit.
        usort($sections, static fn (NavSection $a, NavSection $b): int => $b->priority <=> $a->priority);

        return $sections;
    }

    /**
     * The section whose items contain the current route, or null. Read by the layout to decide
     * which group opens by default.
     *
     * @param list<NavSection>|null $sections the already-built sections, so the layout does not
     *                                        pay for a second pass of permission checks
     */
    public function activeSectionKey(string $currentRoute, ?array $sections = null): ?string
    {
        return $this->activeItem($currentRoute, $sections)['section'];
    }

    /**
     * The route of the active item: the one whose prefix matches AND is the **longest**.
     *
     * ⚠️ Length is what breaks the tie, and the tie is not exotic: as soon as one route name is a
     * prefix of another — `admin_asset_index` and `admin_asset_type_index` — both prefixes match
     * the more specific route, and two entries light up at once. No explicit `activePrefix` can
     * fix it from the outside: every prefix covering the asset routes also covers the asset-type
     * ones. The decision has to look at the items TOGETHER, which a per-item template cannot do.
     *
     * @param list<NavSection>|null $sections the already-built sections, so the layout does not
     *                                        pay for a second pass of permission checks
     */
    public function activeItemRoute(string $currentRoute, ?array $sections = null): ?string
    {
        return $this->activeItem($currentRoute, $sections)['route'];
    }

    /**
     * @param list<NavSection>|null $sections
     *
     * @return array{route: string|null, section: string|null}
     */
    private function activeItem(string $currentRoute, ?array $sections = null): array
    {
        $best = ['route' => null, 'section' => null];
        $bestLength = -1;

        foreach ($sections ?? $this->build() as $section) {
            foreach ($section->items as $item) {
                $prefix = $item->activePrefix();
                $length = \strlen($prefix);

                if (str_starts_with($currentRoute, $prefix) && $length > $bestLength) {
                    $best = ['route' => $item->route, 'section' => $section->key];
                    $bestLength = $length;
                }
            }
        }

        return $best;
    }

    /**
     * Une section est servie quand elle ne réclame aucun espace, ou quand c'est le bon.
     *
     * ⚠️ **Une section SANS espace apparaît partout** — c'est le comportement d'avant, et il doit
     * rester le défaut : la plupart des applications n'ont qu'un espace connecté et ne nommeront
     * jamais `space`.
     *
     * ⚠️ **Un appelant qui ne demande aucun espace reçoit TOUT**, y compris les sections d'un
     * espace nommé. C'est délibéré : un `admin_navigation()` sans argument est ce que fait tout
     * gabarit existant, et le faire soudain ne rien rendre casserait chaque projet à la mise à
     * jour. Une application à deux espaces doit donc passer le sien dans les DEUX layouts — et si
     * elle en oublie un, elle retrouve le défaut, pas une page vide.
     */
    private static function servesSpace(NavSection $section, ?string $space): bool
    {
        return null === $section->space || null === $space || $section->space === $space;
    }

    private function isVisible(?string $permission, ?string $feature): bool
    {
        if (null !== $permission && !$this->authorizationChecker->isGranted($permission)) {
            return false;
        }

        if (null === $feature) {
            return true;
        }

        // Pas de vérificateur branché : on cache. L'inverse rendrait gratuit tout module payant,
        // et la suite de tests resterait verte.
        return $this->features?->isEnabled($feature) ?? false;
    }
}
