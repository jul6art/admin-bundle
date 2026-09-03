<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Navigation\FeatureVisibilityInterface;
use Jul6Art\AdminBundle\Navigation\NavigationBuilder;
use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Jul6Art\AdminBundle\Navigation\NavItem;
use Jul6Art\AdminBundle\Navigation\NavSection;
use Jul6Art\AdminBundle\Tests\Fixtures\AlwaysOffFeatures;
use Jul6Art\AdminBundle\Tests\Fixtures\SpacedNavigation;
use Jul6Art\AdminBundle\Tests\Fixtures\WidgetNavigation;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversNothing]
final class NavigationTest extends AbstractFunctionalTestCase
{
    /**
     * Le seul test qui a besoin du conteneur : il prouve qu'une classe déclarée en service, sans
     * tag, atteint le builder. Le tag `admin.navigation` est posé par l'autoconfiguration
     * d'`AdminBundle` ; l'oublier ne casse rien de visible — le menu est simplement vide.
     */
    public function testAProviderIsCollectedByItsInterfaceAlone(): void
    {
        $builder = $this->boot()->get(NavigationBuilder::class);
        self::assertInstanceOf(NavigationBuilder::class, $builder);

        $sections = $builder->build();

        self::assertNotSame([], $sections, 'Une implémentation du contrat déclarée en service doit suffire.');
        self::assertSame('widgets', $sections[0]->key);
    }

    public function testAnItemWhosePermissionIsRefusedDisappears(): void
    {
        $items = $this->builder(granted: [])->build()[0]->items;

        self::assertSame(['admin_widget_index'], array_map(static fn (NavItem $i): string => $i->route, $items));
    }

    public function testAnItemWhosePermissionIsGrantedStays(): void
    {
        $items = $this->builder(granted: ['widget:secret'])->build()[0]->items;

        self::assertContains('admin_widget_show', array_map(static fn (NavItem $i): string => $i->route, $items));
    }

    /**
     * Une section refusée part entièrement — et sans que ses entrées soient testées une à une, ce
     * qui est aussi ce qui rend la garde de section utile sur un module à quinze liens.
     */
    public function testARefusedSectionTakesItsItemsWithIt(): void
    {
        $keys = array_map(static fn (NavSection $s): string => $s->key, $this->builder()->build());

        self::assertNotContains('secret', $keys);
    }

    public function testAGrantedSectionComesBackAndItsPriorityPutsItFirst(): void
    {
        $keys = array_map(
            static fn (NavSection $s): string => $s->key,
            $this->builder(granted: ['section:secret'])->build(),
        );

        self::assertSame(['secret', 'widgets'], $keys, 'La priorité la plus haute passe devant.');
    }

    /**
     * ⚠️ Le comportement le plus contre-intuitif de la classe, et il vient de Symfony : sans
     * vérificateur de fonctionnalités branché, une entrée qui en cite une est **cachée**. L'inverse
     * rendrait gratuit tout module payant, et la suite resterait verte.
     */
    public function testAFeatureGatedItemIsHiddenWhenNoCheckerIsWired(): void
    {
        $routes = array_map(static fn (NavItem $i): string => $i->route, $this->builder()->build()[0]->items);

        self::assertNotContains('admin_report_index', $routes);
    }

    public function testAFeatureGatedItemAppearsWhenTheCheckerSaysSo(): void
    {
        $builder = $this->builder(features: new AlwaysOffFeatures(['reporting' => true]));
        $routes = array_map(static fn (NavItem $i): string => $i->route, $builder->build()[0]->items);

        self::assertContains('admin_report_index', $routes);
    }

    /**
     * Une section dont toutes les entrées ont disparu disparaît elle aussi : un en-tête de groupe
     * qui n'ouvre sur rien annonce un module que le compte ne peut pas atteindre.
     */
    public function testASectionLeftEmptyIsDropped(): void
    {
        $builder = new NavigationBuilder(
            [new class implements NavigationProviderInterface {
                #[\Override]
                public function sections(): iterable
                {
                    yield new NavSection('empty', 'nav.empty', 'i', [
                        new NavItem('admin_dashboard', 'nav.x', 'i', permission: 'nope'),
                    ]);
                }
            }],
            $this->checker([]),
        );

        self::assertSame([], $builder->build());
    }

    /**
     * L'état actif se décide sur un PRÉFIXE : une page de détail garde allumée l'entrée de sa
     * liste. Sinon la barre latérale se vide dès qu'on ouvre une fiche.
     */
    public function testTheActiveSectionIsFoundByRoutePrefix(): void
    {
        $builder = $this->builder();

        self::assertSame('widgets', $builder->activeSectionKey('admin_widget_show'));
        self::assertNull($builder->activeSectionKey('admin_something_else'));
    }

    /**
     * ⚠️ **Deux entrées dont l'une préfixe l'autre : une seule s'allume.**.
     *
     * La convention « préfixe de nom de route » se retourne dès qu'un nom est le préfixe d'un
     * autre : `admin_asset` matche `admin_asset_type_index` aussi bien que `admin_asset_type`. Sur
     * wovex, « Équipements » et « Types d'équipement » s'allumaient ENSEMBLE (constaté le
     * 2026-08-26), et aucun `activePrefix` explicite ne pouvait les départager — tout préfixe
     * couvrant les routes d'équipement couvre aussi celles des types. Le seul critère qui tranche
     * est la SPÉCIFICITÉ, et elle ne se voit qu'en regardant les entrées ensemble.
     */
    public function testTheMostSpecificItemWinsWhenTwoPrefixesMatch(): void
    {
        $builder = $this->nestedBuilder();

        self::assertSame('admin_asset_type_index', $builder->activeItemRoute('admin_asset_type_index'));
        self::assertSame('admin_asset_index', $builder->activeItemRoute('admin_asset_index'));
        self::assertSame('admin_asset_index', $builder->activeItemRoute('admin_asset_show'), 'Une fiche garde sa liste allumée.');
        self::assertNull($builder->activeItemRoute('admin_something_else'));
    }

    /** La section suit l'entrée gagnante, pas la première qui matche. */
    public function testTheActiveSectionFollowsTheMostSpecificItem(): void
    {
        self::assertSame('types', $this->nestedBuilder()->activeSectionKey('admin_asset_type_index'));
    }

    private function nestedBuilder(): NavigationBuilder
    {
        return new NavigationBuilder(
            [new class implements NavigationProviderInterface {
                #[\Override]
                public function sections(): iterable
                {
                    yield new NavSection('parc', 'nav.parc', 'i', [
                        new NavItem('admin_asset_index', 'nav.assets', 'i'),
                    ], priority: 20);

                    yield new NavSection('types', 'nav.types', 'i', [
                        new NavItem('admin_asset_type_index', 'nav.asset_types', 'i'),
                    ], priority: 10);
                }
            }],
            $this->checker([]),
        );
    }

    /**
     * Une section taguée d'un espace n'apparaît QUE dans cet espace.
     *
     * ⚠️ **Le défaut que `space` ferme** : le registre est GLOBAL — `build()` itère tous les
     * fournisseurs taggés — donc une application à DEUX espaces connectés voyait les deux menus
     * dans les deux. Constaté le 2026-09-03 chez un consommateur : l'espace client affichait deux
     * entrées au libellé IDENTIQUE, l'une menant au back-office, indiscernables avant le clic.
     */
    public function testASectionTaggedWithASpaceAppearsOnlyThere(): void
    {
        $builder = new NavigationBuilder(
            [new SpacedNavigation()],
            $this->checker(['widget:list']),
        );

        self::assertSame(['customer'], self::keysOf($builder->build('customer')));
        self::assertSame(['platform'], self::keysOf($builder->build('platform')));
    }

    /**
     * ⚠️ Une section SANS espace apparaît partout — c'est le comportement d'avant, et il doit
     * rester le défaut : la plupart des applications n'ont qu'un espace connecté et ne nommeront
     * jamais `space`.
     */
    public function testASectionWithNoSpaceAppearsInEverySpace(): void
    {
        $builder = new NavigationBuilder([new WidgetNavigation()], $this->checker(['widget:list']));

        self::assertNotSame([], $builder->build('customer'));
        self::assertNotSame([], $builder->build('platform'));
        self::assertNotSame([], $builder->build());
    }

    /**
     * ⚠️ Un appelant qui ne demande AUCUN espace reçoit tout, sections taguées comprises.
     *
     * C'est délibéré, et c'est le sens de défaillance qui compte : un `admin_navigation()` sans
     * argument est ce que fait tout gabarit existant, et le faire soudain ne rien rendre casserait
     * chaque projet à la mise à jour. Une application qui oublie de passer son espace dans un
     * layout retrouve donc le défaut d'origine — pas une page vide.
     */
    public function testAskingForNoSpaceReturnsEverything(): void
    {
        $builder = new NavigationBuilder([new SpacedNavigation()], $this->checker(['widget:list']));

        self::assertSame(['customer', 'platform'], self::keysOf($builder->build()));
    }

    /**
     * La section RENDUE porte encore son espace.
     *
     * ⚠️ `withItems()` recopie les propriétés POSITIONNELLEMENT, donc une propriété oubliée s'y
     * perd en silence. Et cette perte est **invisible à travers `build()`** : le filtrage par
     * espace a lieu AVANT la reconstruction, donc les bonnes sections sortent quand même. Vérifié
     * le 2026-09-03 — retirer `$this->space` de `withItems()` laisse les trois autres cas de ce
     * fichier au VERT.
     *
     * D'où une assertion sur la propriété elle-même, seul endroit où la perte s'observe. Ce qu'elle
     * protège n'est pas le filtrage mais le gabarit d'un consommateur qui lit `section.space` pour
     * décorer son menu — il recevrait `null` sans que rien ne l'explique.
     */
    public function testTheRenderedSectionStillCarriesItsSpace(): void
    {
        $builder = new NavigationBuilder([new SpacedNavigation()], $this->checker(['widget:list']));

        $sections = $builder->build('customer');
        self::assertCount(1, $sections);

        self::assertSame(
            'customer',
            $sections[0]->space,
            'La section reconstruite doit garder son espace : `withItems()` recopie '
            .'positionnellement, et une propriété oubliée y disparaît sans bruit.',
        );
    }

    /**
     * @param list<NavSection> $sections
     *
     * @return list<string>
     */
    private static function keysOf(array $sections): array
    {
        return array_map(static fn (NavSection $s): string => $s->key, $sections);
    }

    /**
     * @param list<string> $granted
     */
    private function builder(array $granted = [], ?FeatureVisibilityInterface $features = null): NavigationBuilder
    {
        return new NavigationBuilder([new WidgetNavigation()], $this->checker($granted), $features);
    }

    /**
     * @param list<string> $granted
     */
    private function checker(array $granted): AuthorizationCheckerInterface
    {
        return new readonly class($granted) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $granted
             */
            public function __construct(private array $granted)
            {
            }

            #[\Override]
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \is_string($attribute) && \in_array($attribute, $this->granted, true);
            }
        };
    }
}
