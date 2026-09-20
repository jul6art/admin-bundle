<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Keyboard\KeyboardCatalogue;
use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutResolver;
use Jul6Art\AdminBundle\Translation\DeclaredTranslationKeys;
use Jul6Art\AdminBundle\Twig\KeyboardExtension;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Ce que seul un conteneur réel dit du clavier : qu'il se câble, qu'il se désactive, et surtout ce
 * qu'il fait quand l'application ne fournit PAS de magasin.
 *
 * ⚠️ **Ce dernier point est la raison d'être de ce fichier.** Le port est facultatif par dessein, et
 * « facultatif » est une affirmation qu'un test unitaire ne peut pas vérifier : elle porte sur la
 * COMPILATION du conteneur, pas sur une classe. `KeyboardStorePass` retire l'argument quand rien
 * n'implémente l'interface ; sans lui, toute application sans écran de réglage échouerait au boot
 * sur « aucun service n'implémente KeyboardShortcutStoreInterface ».
 */
#[CoversNothing]
final class KeyboardWiringTest extends AbstractFunctionalTestCase
{
    /**
     * ⚠️ Le jeu de fixtures n'enregistre AUCUN magasin, ce qui est le cas nominal : la plupart des
     * applications n'offrent pas de personnalisation. Le conteneur doit donc se compiler, et les
     * combinaisons d'usine s'appliquer.
     */
    public function testItBootsWithoutAStoreAndServesTheFactoryCombos(): void
    {
        $container = $this->boot(withCore: true);

        $resolver = $container->get(KeyboardShortcutResolver::class);
        self::assertInstanceOf(KeyboardShortcutResolver::class, $resolver);

        self::assertSame('n', $resolver->resolve(KeyboardCatalogue::GLOBAL_NEW));
        self::assertSame(
            ['global.new' => 'n', 'form.save' => 'ctrl+enter', 'form.save_and_new' => 'ctrl+shift+enter'],
            $resolver->snapshot(),
        );
    }

    public function testTheTwigHelpersAreRegistered(): void
    {
        $container = $this->boot(withCore: true);

        self::assertInstanceOf(KeyboardExtension::class, $container->get(KeyboardExtension::class));
    }

    /**
     * Une application ajoute ses actions par CONFIGURATION, et le catalogue les sert au même titre
     * que les trois du socle.
     */
    public function testAnApplicationAddsItsOwnActions(): void
    {
        $container = $this->boot('test', [
            'keyboard' => [
                'actions' => [
                    'erp.lines.add' => ['default' => 'l', 'label' => 'keyboard.action.erp_lines_add'],
                ],
            ],
        ], withCore: true);

        $resolver = $container->get(KeyboardShortcutResolver::class);
        self::assertInstanceOf(KeyboardShortcutResolver::class, $resolver);

        self::assertSame('l', $resolver->resolve('erp.lines.add'));
        self::assertArrayHasKey('erp.lines.add', $resolver->snapshot());
    }

    /**
     * ⚠️ Le libellé d'une action déclarée par l'application entre dans les clés DÉCLARÉES : il est
     * lu par le navigateur depuis une valeur, jamais depuis un littéral qu'un scanner verrait.
     */
    public function testADeclaredActionLabelIsAnnouncedToTheTranslationGuard(): void
    {
        $container = $this->boot('test', [
            'keyboard' => [
                'actions' => [
                    'erp.lines.add' => ['default' => 'l', 'label' => 'keyboard.action.erp_lines_add'],
                ],
            ],
        ], withCore: true);

        $declared = $container->get(DeclaredTranslationKeys::class);
        self::assertInstanceOf(DeclaredTranslationKeys::class, $declared);

        $keys = $declared->keys();

        self::assertContains('keyboard.action.erp_lines_add', $keys);
        self::assertContains('keyboard.cheatsheet.title', $keys);
        self::assertContains(
            'keyboard.cheatsheet.global.back',
            $keys,
            'Esc n\'est PAS une action du catalogue : sa ligne d\'antisèche manquerait si la liste en était dérivée.',
        );
    }

    /** `enabled: false` laisse la coquille intacte et le clavier absent. */
    public function testItCanBeSwitchedOff(): void
    {
        $container = $this->boot('test', ['keyboard' => ['enabled' => false]], withCore: true);

        self::assertFalse($container->has(KeyboardShortcutResolver::class));
        self::assertTrue($container->getParameter('admin.enabled'));
    }
}
