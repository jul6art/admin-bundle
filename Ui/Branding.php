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
}
