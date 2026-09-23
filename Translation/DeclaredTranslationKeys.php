<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Translation;

/**
 * The catalogue entries this bundle's JavaScript reads without naming them in a way a scanner can
 * see — handed to a project's `AbstractJsTranslationTestCase` so its guard knows they are alive.
 *
 * ```php
 * protected static function declaredKeys(): array
 * {
 *     return [
 *         ...static::getContainer()->get(DatatableKeys::class)->keys(),
 *         ...static::getContainer()->get(AdminKeys::class)->keys(),
 *     ];
 * }
 * ```
 *
 * ## Why a declaration is needed at all
 *
 * The cheat-sheet reads `this.t('keyboard.cheatsheet.…')` and the capture widget
 * `this.t('keyboard.capture.…')` — literals a scanner does see, but only if it scans this bundle's
 * `assets/`, which a consumer's guard has to be told to do. Declaring them is what makes the
 * consumer's catalogue authoritative rather than approximate.
 *
 * ## ⚠️ What is deliberately NOT here: the action labels
 *
 * `KeyboardAction::$labelKey` is read by a SETTINGS SCREEN, server-side, in whatever domain that
 * screen uses — and by nothing in the browser. The cheat-sheet's contextual section reads
 * `data-shortcut-label`, an already-rendered string, not a key.
 *
 * ⚠️ Listing them here was the first version, and it was wrong in the way that costs the most: the
 * guard would have blessed them as browser keys, so a consumer would have added them to the
 * browser catalogue where nothing reads them — a dead entry certified alive. It is the exact
 * mistake `jul6art/dataflow-bundle` warns about in its own declaration (« c'est `keys()`, jamais
 * `templateKeys()` »), and the first consumer to wire this hit it within the hour.
 */
final readonly class DeclaredTranslationKeys
{
    /**
     * The cheat-sheet's own text. The five rows of the global section plus its chrome.
     *
     * ⚠️ `keyboard.cheatsheet.global.back` described the `Esc` back-jump until 1.20; it now labels
     * `Ctrl+B` (`form.back`), the action that replaced it — same words, "back to the list". The key
     * is kept so no product's catalogue has to change.
     *
     * @var list<string>
     */
    private const array CONTROLLER_KEYS = [
        'keyboard.capture.captured',
        'keyboard.capture.default',
        'keyboard.capture.meta_refused',
        'keyboard.capture.press',
        'keyboard.capture.reset',
        'keyboard.capture.unsupported',
        'keyboard.cheatsheet.close',
        'keyboard.cheatsheet.global.back',
        'keyboard.cheatsheet.global.help',
        'keyboard.cheatsheet.global.new',
        'keyboard.cheatsheet.global.save',
        'keyboard.cheatsheet.global.save_and_new',
        'keyboard.cheatsheet.section.global',
        'keyboard.cheatsheet.section.page',
        'keyboard.cheatsheet.title',
    ];

    /** @return list<string> sorted */
    public function keys(): array
    {
        $keys = self::CONTROLLER_KEYS;
        \sort($keys);

        return $keys;
    }

    /** @return list<string> */
    public function prefixes(): array
    {
        return [];
    }
}
