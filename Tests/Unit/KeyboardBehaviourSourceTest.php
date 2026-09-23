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
     * **A keystroke a control already handled is not routed** (REVIEWER, 2026-09-23). In a Trix
     * editor `Ctrl+B` makes text bold and calls `preventDefault()`; the router then clicked Cancel
     * and the page left with everything typed.
     */
    public function testAHandledKeystrokeIsNotRouted(): void
    {
        self::assertMatchesRegularExpression('/_onKeydown\(event\)\s*\{[\s\S]{0,600}?if\s*\(event\.defaultPrevented\)\s*\{?\s*return/', self::read('keyboard'));
    }

    /**
     * **On macOS, `Ctrl` + a letter in a text field is the system's** (decider, 2026-09-23): the
     * Emacs bindings — `Ctrl+B` moves the caret back one character. Without Shift or Alt only, so
     * `Ctrl+Enter` and a `Ctrl+Shift+…` override still fire.
     */
    public function testMacTextFieldsKeepTheirCtrlLetterBindings(): void
    {
        $source = self::read('keyboard');

        self::assertStringContainsString('_isMac()', $source);
        self::assertMatchesRegularExpression('/this\._isMac\(\)\s*&&\s*event\.ctrlKey\s*&&\s*!event\.shiftKey\s*&&\s*!event\.altKey\s*&&\s*\/\^\[a-z\]\$\/i\.test\(event\.key\)\s*&&\s*this\._isTextEntry\(event\.target\)/', $source);
    }

    /**
     * **No NAVIGATION from a rich editor**: an editor that does not cancel `Ctrl+B` would still lose
     * its content. From a `contenteditable`, a shortcut whose target is a link does nothing.
     */
    public function testARichEditorNeverNavigates(): void
    {
        self::assertMatchesRegularExpression('/isContentEditable[\s\S]{0,200}?_navigates\(target\)/', self::read('keyboard'));
    }

    /**
     * **An open modal, or the cheat-sheet, confines the shortcuts to itself** — `Ctrl+B` under a
     * confirmation dialog clicked the Cancel link of the page behind it.
     */
    public function testAnOpenOverlayConfinesTheShortcuts(): void
    {
        $source = self::read('keyboard');

        self::assertStringContainsString('_openOverlay()', $source);
        self::assertStringContainsString('[aria-modal="true"]', $source);
        self::assertStringContainsString('dialog[open]', $source);
        self::assertStringContainsString('[data-backdrop]', $source, 'datatable-bundle\'s confirmation modal carries only this marker.');
    }

    /**
     * **The cheat-sheet keeps the page's own words**: a combo the page offers is listed with the
     * page's label ("New invoice"), and the generic global row for it is left out.
     */
    public function testThePageLabelWinsOverTheGenericGlobalRow(): void
    {
        self::assertMatchesRegularExpression('/\]\.filter\(\(\[combo\]\)\s*=>\s*!pageCombos\.has\(combo\)\)/', self::read('keyboard'));
    }

    /**
     * **`Ctrl+B` is in the cheat-sheet**, with the combo in force.
     */
    public function testTheBackShortcutIsListedOnce(): void
    {
        $source = self::read('keyboard');

        self::assertStringContainsString("_readCombo('kb.form.back', 'ctrl+b')", $source);
        self::assertStringContainsString('[this._combos.formBack,', $source);
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
