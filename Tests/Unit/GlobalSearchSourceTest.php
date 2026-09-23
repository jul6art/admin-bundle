<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The decisions of the global search controller that a rewrite could silently undo.
 *
 * Same reasoning as `KeyboardBehaviourSourceTest`: no JavaScript runner ships with this bundle, and
 * what is pinned is that each decision is still MADE in the file. They come from the two products
 * that ran this controller as twins before it moved here (cereezer ADR-0031, superp ADR-0012).
 */
#[CoversNothing]
final class GlobalSearchSourceTest extends TestCase
{
    /**
     * **A debounce.** Without it, a word typed at normal speed fires one request per keystroke,
     * each costing several queries on the server.
     */
    public function testTheSearchIsDebounced(): void
    {
        self::assertMatchesRegularExpression('/setTimeout\(\(\) => this\.#run\(\), \d{3}\)/', self::source());
    }

    /**
     * **The request in flight is aborted.** Two close keystrokes fire two requests; without an
     * `AbortController` the slower may answer last and show the results of a term already erased.
     */
    public function testTheRequestInFlightIsAborted(): void
    {
        $source = self::source();

        self::assertStringContainsString('this.pending?.abort()', $source);
        self::assertStringContainsString('new AbortController()', $source);
        self::assertStringContainsString("'AbortError'", $source, 'Une requête annulée n\'est pas une erreur.');
    }

    /**
     * **A threshold of two characters.** The server doubles it — this file can be bypassed, the
     * route cannot.
     */
    public function testNothingIsSearchedBelowTwoCharacters(): void
    {
        self::assertMatchesRegularExpression('/term\.length < 2/', self::source());
    }

    /**
     * **A label comes from the database**: a customer named `<script>` must not be injected as is
     * into `innerHTML`.
     */
    public function testEveryLabelIsEscaped(): void
    {
        self::assertStringContainsString('this.#escape(result.label)', self::source());
    }

    /**
     * **A family may name where "see all" leads** (`SearchGroup::$url`): the "N results" hint then
     * becomes a link to the family's own list, filtered by the same term — the panel points, the
     * list is where one sorts and acts (cegeta ADR-0037). Without a URL it stays a plain hint.
     */
    public function testTheMoreHintLinksToTheFamilyListWhenItHasAUrl(): void
    {
        $source = self::source();

        self::assertStringContainsString('group.url', $source);
        self::assertMatchesRegularExpression('/<a href="\$\{this\.#escape\(group\.url\)\}"/', $source);
    }

    /**
     * **The `/` shortcut spares a field being typed in** — otherwise typing `/` in a description
     * would open the search instead of writing a character.
     */
    public function testTheSlashShortcutSparesAFieldBeingTypedIn(): void
    {
        self::assertMatchesRegularExpression("/\\['INPUT', 'TEXTAREA', 'SELECT'\\]\\.includes/", self::source());
    }

    /**
     * **The `/` shortcut can be turned off** — a product whose keyboard policy refuses it (cegeta
     * ADR-0036/0037) must be able to take the search without the shortcut. On by default: the two
     * products that had it keep it.
     */
    public function testTheSlashShortcutCanBeTurnedOff(): void
    {
        $source = self::source();

        self::assertMatchesRegularExpression('/shortcut:\s*\{\s*type:\s*Boolean,\s*default:\s*true\s*\}/', $source);
        self::assertMatchesRegularExpression('/if\s*\(this\.shortcutValue\)\s*\{\s*document\.addEventListener\(\'keydown\'/', $source, 'Sans la valeur, aucun écouteur clavier n\'est posé.');

        $partial = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/partials/_global_search.html.twig');
        self::assertStringContainsString('data-search--global-shortcut-value="{{ (shortcut ?? true) ? \'true\' : \'false\' }}"', $partial);
    }

    /**
     * **The mobile row closes** on Escape, on the × and on a tap outside — a full-width row that
     * could not be dismissed would hide the page below the header.
     */
    public function testTheMobileRowCanBeDismissed(): void
    {
        $source = self::source();

        self::assertMatchesRegularExpression('/dismiss\(\)\s*\{/', $source);
        self::assertMatchesRegularExpression('/document\.addEventListener\(\'(pointerdown|click)\'/', $source, 'Un appui hors de la zone doit la fermer.');
    }

    /**
     * Each row the controller builds — a result, the "see all" link — carries the touch rule
     * (44 px measured 36 and 16 at 360 px, cegeta 2026-09-23).
     */
    public function testEveryRowTheControllerBuildsIsATouchTarget(): void
    {
        self::assertSame(
            2,
            substr_count(self::source(), 'admin-search-row'),
            'Le lien de résultat ET le lien « voir tout » doivent porter `admin-search-row`.',
        );
    }

    /**
     * The magnifier and the × of the mobile row (38.5 px wide at 360 px, cegeta 2026-09-23).
     */
    public function testTheMobileButtonsAreTouchTargets(): void
    {
        $partial = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/partials/_global_search.html.twig');

        self::assertSame(2, substr_count($partial, 'admin-search-target'), 'La loupe ET la croix doivent porter `admin-search-target`.');
    }

    private static function source(): string
    {
        $path = \dirname(__DIR__, 2).'/assets/controllers/global-search_controller.js';

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
