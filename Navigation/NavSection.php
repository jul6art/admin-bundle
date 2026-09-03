<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

/**
 * A collapsible group of links.
 *
 * `key` is what the open/closed state is remembered under, so it has to be stable and unique
 * across the whole application — two sections sharing a key toggle together, which reads as a bug
 * in the sidebar rather than in the menu declaration.
 */
final readonly class NavSection
{
    /**
     * @param string        $key        stable identifier, used for the persisted open state
     * @param list<NavItem> $items
     * @param string|null   $permission gate for the whole section, on top of each item's own
     * @param string|null   $feature    feature gate for the whole section
     * @param int           $priority   higher comes first; equal priorities keep declaration order
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $icon,
        public array $items,
        public string $labelDomain = 'messages',
        public ?string $permission = null,
        public ?string $feature = null,
        public int $priority = 0,
        /**
         * L'espace connecté auquel cette section appartient, ou `null` pour « tous ».
         *
         * ⚠️ **Le registre de navigation est GLOBAL** : `NavigationBuilder` itère TOUS les
         * fournisseurs taggés, sans notion d'espace. Une application qui a deux espaces connectés
         * — un espace client et un back-office, par exemple — voyait donc les deux menus dans les
         * deux, et un client pouvait cliquer une entrée qui quitte son espace. Constaté le
         * 2026-09-03 : deux entrées au libellé IDENTIQUE côte à côte, indiscernables avant le clic
         * et sur un tiroir de 360 px.
         *
         * ⚠️ **`null` = visible partout, donc le comportement d'AVANT à l'octet près.** Une
         * application à un seul espace connecté — le cas de la plupart — n'a rien à changer.
         *
         * La chaîne elle-même n'a aucun sens pour le bundle : c'est l'application qui la nomme et
         * qui la passe à `admin_navigation()`. Le bundle ne fait que comparer.
         */
        public ?string $space = null,
    ) {
    }

    /**
     * @param list<NavItem> $items
     */
    public function withItems(array $items): self
    {
        return new self(
            $this->key,
            $this->labelKey,
            $this->icon,
            $items,
            $this->labelDomain,
            $this->permission,
            $this->feature,
            $this->priority,
            // ⚠️ À reporter : la copie est POSITIONNELLE, donc une propriété oubliée ici se perd
            // silencieusement au filtrage — la section reviendrait sans son espace et
            // réapparaîtrait partout, ce qui est exactement le défaut que `space` ferme.
            $this->space,
        );
    }
}
