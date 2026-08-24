<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection;

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
                        ->scalarNode('privacy')->defaultValue('')->info('Linked from the cookie banner; empty renders no banner.')->end()
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
            ->end();

        return $treeBuilder;
    }
}
