<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Ui;

/**
 * What makes the shell look like *this* product rather than the bundle's demo: a name, a logo, a
 * favicon, and where the logo links to.
 *
 * It is configuration and not a set of Twig blocks because these four values appear in five places
 * each — sidebar header, sidebar footer, sign-in card, sign-up card, password-reset card — and a
 * project that has to override five blocks to change its logo ends up with four of them right.
 *
 * ```yaml
 * admin:
 *     branding:
 *         name: 'Acme Admin'
 *         logo: 'img/logo.png'          # passed through Twig's asset()
 *         favicon: 'img/favicon.ico'
 *         home_route: admin_dashboard
 * ```
 *
 * ```yaml
 * admin:
 *     branding:
 *         logo: 'img/logo-black.png'    # dark ink, for the LIGHT theme
 *         logo_dark: 'img/logo.png'     # white, for the DARK theme — optional
 * ```
 *
 * `logo` and `favicon` are **asset paths**, not URLs: they go through `asset()`, so versioning and
 * a CDN base path keep working. An absolute URL passed here still renders, and still bypasses both.
 */
final readonly class Branding
{
    public function __construct(
        public string $name,
        public string $logo,
        public string $favicon,
        public string $homeRoute,
        // Largeur du logo sur les pages d'authentification, en pixels. Null = la hauteur fixe
        // historique (h-12) et la largeur suit. Un wordmark large a besoin de plus que 48px.
        public ?int $logoWidth = null,
        // Un logo qui embarque déjà le nom (wordmark) rend la ligne du nom redondante.
        public bool $showName = true,
        /**
         * La variante du logo pour le thème SOMBRE, quand `logo` porte celle du thème clair.
         *
         * ⚠️ Un jeu de marque a souvent DEUX wordmarks, chacun illisible sur l'autre fond : une
         * encre foncée pour le fond clair, un blanc pour le fond sombre. Avec un seul nœud `logo`,
         * un projet en servait un des deux et le mot disparaissait la moitié du temps — le
         * symptôme est un logo réduit à son icône, et il ne se voit qu'à l'écran, dans le thème où
         * personne ne relit.
         *
         * ⚠️ **Vide = comportement ANTÉRIEUR à l'octet près** : une seule image est rendue, sans
         * classe de visibilité. Un projet qui n'a qu'un logo lisible partout n'a rien à changer.
         */
        public string $logoDark = '',
    ) {
    }

    /** Whether a logo was configured at all — the templates fall back to the name alone. */
    public function hasLogo(): bool
    {
        return '' !== $this->logo;
    }

    public function hasFavicon(): bool
    {
        return '' !== $this->favicon;
    }

    /**
     * Whether a distinct dark-theme logo was configured.
     *
     * ⚠️ The templates render BOTH images when this is true and let CSS hide one, rather than
     * choosing in Twig. The base template's anti-FOUC script can flip the `dark` class AFTER the
     * server rendered — a `theme: system` account resolves in the browser — so a choice made in
     * Twig would be the wrong one, and frozen.
     */
    public function hasLogoDark(): bool
    {
        return '' !== $this->logoDark;
    }
}
