<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection;

use Jul6Art\AdminBundle\Ui\EmailPalette;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The bundle's configuration tree.
 *
 * Write an `->info()` on every node: it is what `config:dump-reference` shows, and it is the only
 * documentation a reader gets before opening the code.
 *
 * > ⚠️ **A node that decides something at compile time cannot be an env var.** `%env(bool:X)%`
 * > reaches a `booleanNode()` as the placeholder *string* and the config layer rejects it. Use a
 * > plain value for anything that gates service registration, and keep env vars for values passed
 * > through to a service at runtime (a `scalarNode` argument).
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('admin');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Registers the bundle\'s services. false leaves it installed and inert.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('base_template')
                    ->info('The template `@Admin/layout.html.twig` extends. Default: the bundle\'s own. An application that already has a base — asset tags, its own meta tags, a cookie banner — points this at it, and makes THAT template extend `@Admin/base.html.twig`. Without this indirection an admin page would bypass the application\'s base entirely, and the symptom is a page rendered with no stylesheet at all.')
                    ->defaultValue('@Admin/base.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('layout_template')
                    ->info('The layout that the bundle\'s own PAGES extend (the performance dashboard today). Default: the bundle\'s `@Admin/layout.html.twig`. An application whose pages go through its own layout — the one exposing `window.jwtToken`, an extra top bar… — points this at it, and makes THAT layout extend `@Admin/layout.html.twig`.')
                    ->defaultValue('@Admin/layout.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('branding')
                    ->info('The four values that make the shell look like your product rather than the bundle\'s demo. They appear in the sidebar, the sign-in card and the head — hence configuration rather than five Twig blocks to override.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')
                            ->info('Product name, shown next to the logo and used as the default page title.')
                            ->defaultValue('Admin')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('logo')
                            ->info('Asset path of the logo, passed through Twig\'s asset(). Empty renders the name alone.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('logo_dark')
                            ->info('Asset path of the logo variant for the DARK theme, when `logo` carries the light one. Empty renders a single logo, exactly as before.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('favicon')
                            ->info('Asset path of the favicon, passed through asset(). Empty renders no <link rel="icon">.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('home_route')
                            ->info('Route the logo links to, and where a signed-in account lands.')
                            ->defaultValue('admin_dashboard')
                            ->cannotBeEmpty()
                        ->end()
                        // ⚠️ `scalarNode` et non `integerNode`, et ce n'est pas un relâchement.
                        //
                        // `integerNode()->defaultNull()` REFUSE un null explicite : le défaut ne
                        // s'applique qu'en l'ABSENCE de la clé, et un `logo_width: ~` lève
                        // « Expected int, but got null » à la compilation du conteneur. Or l'`info`
                        // ci-dessous invite précisément à ce null. Un mode du
                        // `symfony-skeleton-generator` écrivait donc `~` en toute bonne foi :
                        // AUCUN projet généré ne bootait, `debug:router` compris (2026-08-24).
                        //
                        // Le domaine de la valeur est `null | int >= 1`, ce qu'aucun nœud typé de
                        // Symfony n'exprime : `ScalarNode` accepte le null, `IntegerNode` accepte
                        // l'entier. On garde le premier et on valide le second à la main — la borne
                        // `min(1)` devient la moitié droite de la condition.
                        //
                        // La règle générale, elle, ne dépend pas de ce nœud : **un nœud dont le
                        // défaut est null doit accepter un null explicite**, sinon sa propre
                        // documentation invite à ce qu'il refuse.
                        ->scalarNode('logo_width')
                            ->info('Width in pixels of the logo on the authentication pages. Null — or the key left out — keeps the historical fixed height (h-12) and lets the width follow.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_int($value) || $value < 1))
                                ->thenInvalid('admin.branding.logo_width expects null or a positive integer, %s given.')
                            ->end()
                        ->end()
                        ->booleanNode('show_name')
                            ->info('Whether the product name is written under the logo on the authentication pages. Turn it off when the logo is a wordmark — the name would be there twice.')
                            ->defaultTrue()
                        ->end()
                        ->append($this->emailPaletteNode())
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->info('Route names the shipped shell and auth pages link to. Every one of them has a default matching the names this bundle\'s own controllers declare; an application that names its routes otherwise overrides the ones it has and leaves the rest empty — an empty name hides the link instead of failing the render. WARNING: this table is GLOBAL, one route per entry for the whole application. An application with several areas — /admin and /organization, say — where the same screen exists twice must leave that entry EMPTY and let each layout add its own link in the admin_account_menu_extra block. A single value would send everyone to the same place, and the audience of the other area to a 403.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('login')->defaultValue('admin_security_login')->end()
                        // Vide par défaut, à dessein : l'écran du profileur n'existe que si le
                        // projet importe ses routes, et il n'a de sens qu'en développement. Le
                        // remplir `when@dev` suffit à ne montrer le lien que là.
                        ->scalarNode('performance')->defaultValue('')->end()
                        ->scalarNode('logout')->defaultValue('admin_security_logout')->end()
                        ->scalarNode('register')->defaultValue('')->info('Empty closes public sign-up: the link disappears from the sign-in card.')->end()
                        ->scalarNode('reset_password_request')->defaultValue('')->end()
                        ->scalarNode('profile')->defaultValue('')->end()
                        ->scalarNode('change_password')->defaultValue('')->end()
                        ->scalarNode('appearance')->defaultValue('admin_account_appearance_edit')->info('Empty removes the appearance entry from the account menu.')->end()
                        ->scalarNode('two_factor')->defaultValue('admin_account_two_factor')->info('The account menu entry of the two-factor screen, shown only when `two_factor.enabled` is on. Empty removes the entry — an application with several areas links it from `admin_account_menu_extra` instead.')->end()
                        ->scalarNode('privacy')->defaultValue('')->info('Linked from the cookie banner; empty renders no banner.')->end()
                    ->end()
                ->end()
                ->arrayNode('keyboard')
                    ->info('Keyboard shortcuts for fast data entry. The bundle ships three actions — open a creation screen, save, save and start another — and routes them from the <body>. Disabling this leaves the controllers unregistered and every screen behaves exactly as before.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('false leaves the Twig helpers unregistered; a template calling keyboard_shortcut() then fails loudly rather than rendering a dead attribute.')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('actions')
                            ->info('Actions THIS application adds to the catalogue, keyed by a dotted code (`erp.lines.add`). A code that matches a built-in redefines its default combo instead of adding a second entry. Each entry needs the combo it ships with and the translation key of its label — both are read by the cheat-sheet and by a settings screen.')
                            ->useAttributeAsKey('code')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('default')
                                        ->info('Canonical combo: lower case, modifiers in the order ctrl > shift > alt, then the key. `l`, `ctrl+shift+enter`. Anything else is refused at runtime by KeyboardComboNormalizer and the action falls back to being unbound.')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('label')
                                        ->info('Translation key shown in the cheat-sheet and the settings form. It is read by JavaScript, so it belongs to the catalogue the browser receives.')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mercure')
                    ->info('The two meta tags the real-time front end reads. Both empty means no live refresh, and the datatables simply stop reloading on their own.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('hub_url')
                            ->info('Public URL of the Mercure hub. An env var placeholder is fine here — it is only ever printed.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('token_route')
                            ->info('Route minting the subscriber JWT and returning the authoritative topic list.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('user_guide')
                    ->info('Where the product\'s user guide lives. Filled, the shell links to it from the account menu and from a help icon in the top bar, both opening a new tab; empty — the default — renders nothing. Like `routes`, it is GLOBAL: one guide for every area of the application.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('route')
                            ->info('A route of the application. Parameters the current request already carries — `_locale`, say — are filled in by the router.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('url')
                            ->info('An address instead of a route: absolute, or a path served by the current host.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $guide): bool => '' !== ($guide['route'] ?? '') && '' !== ($guide['url'] ?? ''))
                        ->thenInvalid('admin.user_guide takes a route OR an url, not both: %s.')
                    ->end()
                ->end()
                ->append($this->twoFactorNode())
            ->end();

        return $treeBuilder;
    }

    /**
     * `two_factor`: the optional second factor — a six-digit code from an authenticator app (TOTP),
     * asked after the password. Two decisions, the application's: OFFER it to its accounts
     * (`enabled`), and REQUIRE it (`required`).
     */
    private function twoFactorNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('two_factor')->getRootNode();
        $node
            ->info('The optional two-factor authentication (scheb/2fa-bundle, TOTP). Off by default: nothing is registered. The application adds the `two_factor` block to its sign-in firewall, imports the routes and uses `TwoFactorTrait` on its User — see the README.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('The application OFFERS the second factor: an account turns it on from its account menu, and is then asked for a code after its password.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('required')
                    ->info('The application REQUIRES it: an account without the second factor reaches its enrolment screen and nothing else, and cannot turn it off. Needs `enabled`.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('backup_codes')
                    ->info('Single-use backup codes, for a lost phone: drawn at activation, redrawn on demand, typed instead of the code at sign-in and on the account screen. Needs `enabled`, scheb/2fa-backup-code, and `TwoFactorBackupCodesTrait` on the User.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('issuer')
                    ->info('The name the authenticator app shows next to the account. Null: `branding.name`.')
                    ->defaultNull()
                ->end()
                ->arrayNode('exempt_routes')
                    ->info('Routes whose sign-in skips the code — the development login link (`dev_login_link_check`). Never a route that exists in production.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('enrolment_route')
                    ->info('With `required`: where an account without the second factor is sent — the screen\'s route, prefixed when the application imported it per area (`app_admin_account_two_factor`).')
                    ->defaultValue('admin_account_two_factor')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('open_routes')
                    ->info('With `required`: what an account without the second factor still reaches, beside its enrolment screen and the sign-out — a language switch, say.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $twoFactor): bool => true === ($twoFactor['required'] ?? false) && true !== ($twoFactor['enabled'] ?? false))
                ->thenInvalid('admin.two_factor.required needs admin.two_factor.enabled: an application cannot require what it does not offer, %s.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $twoFactor): bool => true === ($twoFactor['backup_codes'] ?? false) && true !== ($twoFactor['enabled'] ?? false))
                ->thenInvalid('admin.two_factor.backup_codes needs admin.two_factor.enabled: backup codes stand in for a second factor the application offers, %s.')
            ->end();

        return $node;
    }

    /**
     * `branding.email`: the colours of the e-mail layout ({@see EmailPalette}). Every key defaults
     * to the layout's historical colour, so a product that declares nothing sees no change.
     */
    private function emailPaletteNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('email')->getRootNode();
        $children = $node
            ->info('Colours of the transactional e-mail layout (@Admin/email/base.html.twig), as #rrggbb literals — a mail client loads no stylesheet. The defaults are the historical slate layout. A palette whose body text on the panel, heading on the background or button text on the accent falls under 4.5:1 is refused.')
            ->addDefaultsIfNotSet()
            ->children();

        foreach (EmailPalette::DEFAULTS as $key => $default) {
            if ('logo' === $key) {
                $children->enumNode('logo')
                    ->info('Which logo variant the e-mail serves: `light` (branding.logo, dark ink) or `dark` (branding.logo_dark, for a dark e-mail).')
                    ->values(['light', 'dark'])
                    ->defaultValue($default)
                ->end();

                continue;
            }

            $children->scalarNode($key)
                ->defaultValue($default)
                ->validate()
                    ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || 1 !== preg_match('/^#[0-9a-fA-F]{6}$/', $value))
                    ->thenInvalid(\sprintf('admin.branding.email.%s expects a #rrggbb colour, %%s given.', $key))
                ->end()
            ->end();
        }

        $node->validate()
            ->ifTrue(static fn (array $palette): bool => [] !== self::illegiblePairs($palette))
            ->then(static function (array $palette): never {
                throw new \InvalidArgumentException(\sprintf('admin.branding.email is not legible (%s): a mail must reach %.1f:1.', implode(', ', self::illegiblePairs($palette)), EmailPalette::MINIMUM_CONTRAST));
            })
        ->end();

        return $node;
    }

    /**
     * The pairs of an e-mail palette that fall under {@see EmailPalette::MINIMUM_CONTRAST}, as
     * readable failures — empty when the palette is legible.
     *
     * @param array<mixed> $palette
     *
     * @return list<string>
     */
    private static function illegiblePairs(array $palette): array
    {
        $colour = static fn (string $key): string => \is_string($palette[$key] ?? null) ? $palette[$key] : EmailPalette::DEFAULTS[$key];
        $failures = [];
        foreach (EmailPalette::LEGIBLE_PAIRS as [$foreground, $background]) {
            $ratio = EmailPalette::contrast($colour($foreground), $colour($background));
            if ($ratio < EmailPalette::MINIMUM_CONTRAST) {
                $failures[] = \sprintf('%s on %s is %.2f:1', $foreground, $background, $ratio);
            }
        }

        return $failures;
    }
}
