<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Translation;

use Jul6Art\AdminBundle\Keyboard\KeyboardCatalogue;

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
 * ⚠️ The cheat-sheet's labels are read through `this.t('keyboard.cheatsheet.…')`, which a scanner
 * does see. **The ACTION labels are not**: they come from `data-shortcut-label`, written by a
 * template from a key the application chose. And the labels of the configured actions are read by
 * a settings screen from `KeyboardAction::$labelKey` — a value, not a literal. A guard that only
 * scanned source would report every one of them as dead, and the next person to tidy the catalogue
 * would delete the labels of the shortcuts.
 */
final readonly class DeclaredTranslationKeys
{
    /**
     * The cheat-sheet's own text. The five rows of the global section plus its chrome.
     *
     * ⚠️ `keyboard.cheatsheet.global.back` describes `Esc`, which is NOT an overridable action and
     * therefore has no entry in the catalogue — it would be missing if this list were derived from
     * the catalogue alone.
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

    public function __construct(
        private KeyboardCatalogue $catalogue,
    ) {
    }

    /** @return list<string> sorted and deduplicated */
    public function keys(): array
    {
        $keys = self::CONTROLLER_KEYS;

        foreach ($this->catalogue->all() as $action) {
            $keys[] = $action->labelKey;
        }

        $keys = \array_values(\array_unique($keys));
        \sort($keys);

        return $keys;
    }

    /** @return list<string> */
    public function prefixes(): array
    {
        return [];
    }
}
