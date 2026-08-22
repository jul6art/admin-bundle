<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Twig;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Contract\AdminUserInterface;
use Jul6Art\AdminBundle\Contract\AppearanceAwareInterface;
use Jul6Art\AdminBundle\Navigation\NavigationBuilder;
use Jul6Art\AdminBundle\Navigation\NavSection;
use Jul6Art\AdminBundle\Ui\Branding;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * What the shell templates need from PHP: the product's identity, and the menu the signed-in
 * account is allowed to see.
 *
 * All of it is exposed as functions rather than globals so it is computed only on the pages that
 * render the layout — building the navigation costs one permission check per entry, and an API
 * response has no sidebar.
 *
 * @phpstan-type RouteMap array<string, string>
 */
final readonly class AdminUiExtension
{
    public function __construct(
        private Branding $branding,
        private NavigationBuilder $navigation,
        private string $baseTemplate = '@Admin/base.html.twig',
        private string $mercureHubUrl = '',
        private string $mercureTokenRoute = '',
        /** @var array<string, string> */
        private array $routes = [],
    ) {
    }

    #[AsTwigFunction(name: 'admin_branding')]
    public function branding(): Branding
    {
        return $this->branding;
    }

    /**
     * @return list<NavSection>
     */
    #[AsTwigFunction(name: 'admin_navigation')]
    public function navigation(): array
    {
        return $this->navigation->build();
    }

    /**
     * The signed-in account's colour mode as a bare string, or `''` when there is nobody or the
     * account does not carry one.
     *
     * Separate from `admin_appearance_attributes()` because the two land in different places: the
     * attributes on `<html>`, this one inside the `class` attribute. A template that tried to read
     * `app.user.colorMode` directly would break on the anonymous page it also renders.
     */
    #[AsTwigFunction(name: 'admin_color_mode')]
    public function colorMode(?object $user): string
    {
        return $user instanceof AppearanceAwareInterface ? $user->getColorMode()->value : '';
    }

    /**
     * The template the admin layout extends.
     *
     * Twig resolves `{% extends %}` with an expression, so this is what lets an application slot
     * its own base — asset tags, its own meta tags, a cookie banner — under the shell. The
     * alternative, hard-coding `@Admin/base.html.twig`, means an admin page silently bypasses the
     * application's base: no stylesheet, and a layout that looks broken for a reason nothing points
     * at.
     */
    #[AsTwigFunction(name: 'admin_base_template')]
    public function baseTemplate(): string
    {
        return $this->baseTemplate;
    }

    /**
     * A configured route name, or `''`.
     *
     * The empty string is the point: the shell renders a link only when the application says where
     * it goes. A `path('')` would be a 500 on every page; a missing key must hide the link, not
     * break the layout.
     */
    #[AsTwigFunction(name: 'admin_route')]
    public function route(string $key): string
    {
        $value = $this->routes[$key] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * How the shell names the account. Falls back to the security identifier, which is always
     * there and is usually an e-mail address.
     */
    #[AsTwigFunction(name: 'admin_user_label')]
    public function userLabel(?object $user): string
    {
        if ($user instanceof AdminUserInterface) {
            return $user->getDisplayName();
        }

        return $user instanceof UserInterface ? $user->getUserIdentifier() : '';
    }

    /** Two letters for the avatar placeholder, derived from the label when not provided. */
    #[AsTwigFunction(name: 'admin_user_initials')]
    public function userInitials(?object $user): string
    {
        if ($user instanceof AdminUserInterface) {
            return $user->getInitials();
        }

        $label = $this->userLabel($user);

        return '' === $label ? '?' : mb_strtoupper(mb_substr($label, 0, 1));
    }

    /** Asset path of the avatar, or `''` for the initials placeholder. */
    #[AsTwigFunction(name: 'admin_user_avatar')]
    public function userAvatar(?object $user): string
    {
        return $user instanceof AdminUserInterface ? (string) $user->getAvatarPath() : '';
    }

    /**
     * Accent value → reference colour, for the swatches of the settings form.
     *
     * A function rather than `choice.vars.value.swatch()` because the `value` of an expanded
     * `ChoiceType` child is the view **string**, not the enum case — calling a method on it throws
     * at render time, and only on the one page that renders this form.
     *
     * @return array<string, string>
     */
    #[AsTwigFunction(name: 'admin_accent_swatches')]
    public function accentSwatches(): array
    {
        $swatches = [];
        foreach (AccentColor::cases() as $accent) {
            $swatches[$accent->value] = $accent->swatch();
        }

        return $swatches;
    }

    /** Public URL of the Mercure hub, or `''` when live refresh is not configured. */
    #[AsTwigFunction(name: 'admin_mercure_hub_url')]
    public function mercureHubUrl(): string
    {
        return $this->mercureHubUrl;
    }

    /** Route minting the subscriber token, or `''`. */
    #[AsTwigFunction(name: 'admin_mercure_token_route')]
    public function mercureTokenRoute(): string
    {
        return $this->mercureTokenRoute;
    }

    /**
     * The `<html>` attributes carrying the signed-in account's appearance, or an empty string.
     *
     * Rendering them server-side is what avoids the flash of the wrong theme: the page arrives
     * already dark. The small script in the head only reconciles `system` — which needs
     * `matchMedia` and therefore cannot be known server-side.
     *
     * Returns nothing at all for an anonymous visitor, or for an account that does not implement
     * the contract. A `data-accent=""` would be worse than no attribute: the CSS selector
     * `[data-accent='emerald']` does not match it, but `[data-accent]` does, and someone will
     * eventually write the second one.
     */
    #[AsTwigFunction(name: 'admin_appearance_attributes', isSafe: ['html'])]
    public function appearanceAttributes(?object $user): string
    {
        if (!$user instanceof AppearanceAwareInterface) {
            return '';
        }

        $attributes = [
            'data-theme' => $user->getColorMode()->value,
            'data-accent' => $user->getAccent()->value,
            'data-density' => $user->getDensity()->value,
            'data-font' => $user->getFontScale()->value,
        ];

        if ($user->isHighContrast()) {
            $attributes['data-contrast'] = 'high';
        }

        if ($user->isReducedMotion()) {
            $attributes['data-motion'] = 'reduced';
        }

        $rendered = '';
        foreach ($attributes as $name => $value) {
            // Les valeurs viennent toutes d'enums fermées, donc l'échappement est une ceinture ;
            // il coûte deux microsecondes et retire la question.
            $rendered .= \sprintf(' %s="%s"', $name, htmlspecialchars($value, \ENT_QUOTES, 'UTF-8'));
        }

        return $rendered;
    }

    /**
     * The section to open by default: the one containing the current page.
     *
     * Passing the already-built sections back in avoids rebuilding them — the layout calls
     * `admin_navigation()` first, and rebuilding would run every permission check twice.
     *
     * @param list<NavSection> $sections
     */
    #[AsTwigFunction(name: 'admin_active_section')]
    public function activeSection(string $currentRoute, array $sections): ?string
    {
        return $this->navigation->activeSectionKey($currentRoute, $sections);
    }
}
