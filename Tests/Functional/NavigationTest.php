<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Navigation\FeatureVisibilityInterface;
use Jul6Art\AdminBundle\Navigation\NavigationBuilder;
use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Jul6Art\AdminBundle\Navigation\NavItem;
use Jul6Art\AdminBundle\Navigation\NavSection;
use Jul6Art\AdminBundle\Tests\Fixtures\AlwaysOffFeatures;
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
