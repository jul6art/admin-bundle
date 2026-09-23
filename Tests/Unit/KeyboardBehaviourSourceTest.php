<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Four behaviours of the keyboard controllers that were each paid for by a bug report, and that a
 * refactor could silently undo.
 *
 * ## Why a source guard rather than a browser test
 *
 * This bundle ships no JavaScript runner, and adding one to assert four branches would cost more
 * than it protects. What these cases pin is not *how* the code is written — it is that four
 * decisions are still MADE somewhere in the file. Each comes from a dated report in `superp`, the
 * product where this system ran for five months before it moved here, and none of them is
 * obvious enough to survive a rewrite by accident.
 *
 * ⚠️ **They arrive with the code.** Before 2026-09-20 they lived in `superp`, asserting on that
 * project's own copy of these controllers. Leaving them there after the move would have made a
 * consumer assert on `vendor/` — and would have left the bundle free to regress for everyone else.
 */
#[CoversNothing]
final class KeyboardBehaviourSourceTest extends TestCase
{
    /**
     * **`Enter` on a checkbox or radio TOGGLES it** (superp, report 2026-06-04 § P4).
     *
     * Natively `Enter` never checks a box — only `Space` does. Merely blocking the implicit submit
     * left the box feeling dead under keyboard navigation, which is the opposite of what this whole
     * feature exists for.
     */
    public function testEnterTogglesACheckboxRatherThanJustBlockingTheSubmit(): void
    {
        self::assertMatchesRegularExpression(
            "/\\['checkbox', 'radio'\\]\\.includes\\([\\w.]+\\.type\\)[\\s\\S]*?\\.click\\(\\)/",
            self::read('submit-shortcut'),
            'Enter doit BASCULER la case, pas seulement empêcher la soumission.',
        );
    }

    /**
     * **The `Enter` guard spares buttons** (superp, report 2026-06-01 § P2).
     *
     * A focused button's native `Enter` action is to activate itself. The first version used a
     * "everything that is not text" rule, which swallowed that activation — and made every button
     * unreachable by keyboard, on a feature built for keyboard users.
     */
    public function testTheEnterGuardTargetsOnlyCheckboxesRadiosAndSelects(): void
    {
        $source = self::read('submit-shortcut');

        self::assertMatchesRegularExpression("/\\['checkbox', 'radio'\\]\\.includes\\([\\w.]+\\.type\\)/", $source);
        self::assertStringContainsString("=== 'SELECT'", $source);
        self::assertStringNotContainsString(
            "'button', 'submit', 'reset'",
            $source,
            'Le garde ne doit PAS énumérer les boutons : les épargner est le correctif, pas les lister.',
        );
    }

    /**
     * **`Escape` no longer navigates** (decider, 2026-09-23): it only closes the cheat-sheet.
     *
     * It used to jump back to "the list one arrived from" — but only when one had arrived through a
     * shortcut, so a mouse user pressed it for nothing; it could not be overridden; and `Escape` is
     * the key every Select2, modal and picker already owns, so one press too many left a half-filled
     * form. `Ctrl+B` (`form.back`) replaces it: explicit, listed, overridable.
     */
    public function testEscapeNoLongerNavigates(): void
    {
        $source = self::read('keyboard');

        self::assertStringNotContainsString('location.assign', $source, 'Échap ne doit plus quitter la page.');
        self::assertStringNotContainsString('kb.originUrl', $source, 'Plus aucune origine mémorisée : rien ne la relit.');
        self::assertMatchesRegularExpression('/_onEscape\(event\)\s*\{\s*if\s*\(this\._cheatsheet\)/', $source, 'Échap ferme encore l\'antisèche.');
    }

    /**
     * **`Ctrl+B` is in the cheat-sheet**, with the combo in force, and the page section does not list
     * it a second time because the Cancel link it clicks carries a label too.
     */
    public function testTheBackShortcutIsListedOnce(): void
    {
        $source = self::read('keyboard');

        self::assertStringContainsString("_readCombo('kb.form.back', 'ctrl+b')", $source);
        self::assertStringContainsString('[this._combos.formBack,', $source);
        self::assertMatchesRegularExpression('/globalCombos\.has\(combo\)/', $source, 'La section « page » ne répète pas une combinaison déjà listée en global.');
    }

    /**
     * **The cheat-sheet shows the combos IN FORCE, not the factory ones** (superp, report
     * 2026-05-31-4 § P4).
     *
     * It listed hard-coded `n` and `Ctrl+Enter` while the user had rebound them — documentation
     * that contradicts the software it documents is worse than none.
     */
    public function testTheCheatSheetReadsTheResolvedCombos(): void
    {
        $source = self::read('keyboard');

        self::assertStringContainsString("_readCombo('kb.global.new'", $source);
        self::assertStringContainsString("_readCombo('kb.form.save'", $source);
        self::assertStringContainsString("_readCombo('kb.form.save_and_new'", $source);
        self::assertStringContainsString('[this._combos.globalNew,', $source);
        self::assertStringContainsString('[this._combos.formSave,', $source);
        self::assertStringContainsString('[this._combos.formSaveAndNew,', $source);
    }

    private static function read(string $controller): string
    {
        $path = \dirname(__DIR__, 2).'/assets/controllers/'.$controller.'_controller.js';

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
