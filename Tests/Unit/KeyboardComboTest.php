<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\Keyboard\KeyboardCatalogue;
use Jul6Art\AdminBundle\Keyboard\KeyboardComboNormalizer;
use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutResolver;
use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutStoreInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The combo grammar, and what the resolver does when the stored value does not obey it.
 *
 * ⚠️ **This grammar is implemented three times** — here, in `keyboard_controller.js`
 * (`_comboFromEvent`) and in `shortcut-capture_controller.js`. The three must agree. A combination
 * the capture widget accepts and the router cannot build is a preference the user saved and that
 * never fires, with nothing on screen to explain it. This file is the one that pins it.
 */
#[CoversNothing]
final class KeyboardComboTest extends TestCase
{
    #[DataProvider('combos')]
    public function testTheCanonicalFormIsIndependentOfHowItWasTyped(string $input, ?string $expected): void
    {
        self::assertSame($expected, new KeyboardComboNormalizer()->normalize($input));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function combos(): iterable
    {
        yield 'une lettre' => ['l', 'l'];
        yield 'la casse ne compte pas' => ['CTRL+Shift+L', 'ctrl+shift+l'];
        yield 'l\'ordre des modificateurs est normalisé' => ['alt+ctrl+m', 'ctrl+alt+m'];
        yield 'les espaces sont tolérés' => [' ctrl + enter ', 'ctrl+enter'];
        yield 'une touche de fonction' => ['f7', 'f7'];
        yield 'espace' => ['shift+space', 'shift+space'];

        // ⚠️ `meta` est refusé : sa signification change d'un système à l'autre, et un raccourci
        // qui marche sur une machine et ne fait rien sur une autre est pire que pas de raccourci.
        yield 'cmd est hors vocabulaire' => ['meta+s', null];
        // ⚠️ Les flèches et Tab déplacent le focus : les voler casse la navigation au clavier pour
        // exactement les gens à qui cette fonctionnalité s'adresse.
        yield 'une flèche n\'est pas un raccourci' => ['ctrl+arrowup', null];
        yield 'tab non plus' => ['tab', null];
        yield 'f13 n\'existe pas' => ['f13', null];
        yield 'un modificateur répété est refusé, pas dédoublonné' => ['ctrl+ctrl+l', null];
        yield 'un modificateur seul n\'est pas une combinaison' => ['ctrl', null];
        yield 'vide' => ['', null];
    }

    /**
     * ⚠️ **Signalées, jamais arbitrées.** Deux actions sur la même combinaison est une configuration
     * que l'utilisateur a faite et dont il doit être averti ; désigner un gagnant ici rendrait l'un
     * de ses deux raccourcis silencieusement mort.
     */
    public function testACollisionIsReportedRatherThanResolved(): void
    {
        $duplicates = new KeyboardComboNormalizer()->duplicates([
            'form.save' => 'ctrl+enter',
            'erp.lines.add' => 'l',
            'crm.note.add' => 'l',
            'unbound' => null,
        ]);

        self::assertSame(['l'], $duplicates);
    }

    /**
     * **Sans magasin, les combinaisons d'usine.**.
     *
     * ⚠️ C'est l'inverse du port des préférences de tableau, dont l'absence retire la fonctionnalité
     * en silence. Un back-office qui cesserait de répondre à `Ctrl+Entrée` parce que personne n'a
     * câblé de base de données serait indiscernable d'un back-office cassé.
     */
    public function testWithoutAStoreTheFactoryCombosApply(): void
    {
        $resolver = new KeyboardShortcutResolver(new KeyboardCatalogue(), new KeyboardComboNormalizer());

        self::assertSame('n', $resolver->resolve(KeyboardCatalogue::GLOBAL_NEW));
        self::assertSame('ctrl+enter', $resolver->resolve(KeyboardCatalogue::FORM_SAVE));
        self::assertSame('ctrl+shift+enter', $resolver->resolve(KeyboardCatalogue::FORM_SAVE_AND_NEW));
    }

    public function testAStoredOverrideWins(): void
    {
        $resolver = $this->resolverWith([KeyboardCatalogue::GLOBAL_NEW => 'CTRL+Shift+N']);

        self::assertSame('ctrl+shift+n', $resolver->resolve(KeyboardCatalogue::GLOBAL_NEW), 'Une valeur stockée est normalisée, pas recopiée.');
        self::assertSame('ctrl+enter', $resolver->resolve(KeyboardCatalogue::FORM_SAVE), 'Ce qui n\'est pas surchargé garde son défaut.');
    }

    /**
     * ⚠️ **Une surcharge illisible est IGNORÉE, pas réparée.** Une valeur stockée peut précéder un
     * changement de grammaire, ou venir d'une ligne éditée à la main. La donner au routeur
     * produirait un `data-shortcut` qu'aucune frappe ne peut atteindre — donc un raccourci que
     * l'antisèche documente et qui ne fait rien.
     */
    public function testAnUnreadableOverrideFallsBackToTheDefault(): void
    {
        $resolver = $this->resolverWith([KeyboardCatalogue::GLOBAL_NEW => 'meta+shift+@']);

        self::assertSame('n', $resolver->resolve(KeyboardCatalogue::GLOBAL_NEW));
    }

    /** ⚠️ Un code inconnu rend `''` : la faute est dans un gabarit, et elle doit masquer le raccourci, pas casser la page. */
    public function testAnUnknownCodeIsSilent(): void
    {
        $resolver = new KeyboardShortcutResolver(new KeyboardCatalogue(), new KeyboardComboNormalizer());

        self::assertSame('', $resolver->resolve('nothing.like.this'));
    }

    /**
     * Une application ajoute ses propres actions — et peut redéfinir le défaut d'une action du
     * socle sans en créer une seconde.
     */
    public function testAnApplicationExtendsAndRedefinesTheCatalogue(): void
    {
        $catalogue = new KeyboardCatalogue([
            'erp.lines.add' => ['default' => 'l', 'label' => 'keyboard.action.erp_lines_add'],
            KeyboardCatalogue::GLOBAL_NEW => ['default' => 'c', 'label' => 'keyboard.action.global_new'],
        ]);

        self::assertCount(4, $catalogue->all(), 'Redéfinir une action du socle ne doit pas en créer une seconde.');
        self::assertSame('l', $catalogue->defaultFor('erp.lines.add'));
        self::assertSame('c', $catalogue->defaultFor(KeyboardCatalogue::GLOBAL_NEW));
    }

    /** @param array<string, string> $overrides */
    private function resolverWith(array $overrides): KeyboardShortcutResolver
    {
        $store = new class($overrides) implements KeyboardShortcutStoreInterface {
            /** @param array<string, string> $overrides */
            public function __construct(private readonly array $overrides)
            {
            }

            #[\Override]
            public function overrideFor(string $code): ?string
            {
                return $this->overrides[$code] ?? null;
            }
        };

        return new KeyboardShortcutResolver(new KeyboardCatalogue(), new KeyboardComboNormalizer(), $store);
    }
}
