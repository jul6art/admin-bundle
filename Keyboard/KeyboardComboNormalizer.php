<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Keyboard;

/**
 * Normalises and validates a keyboard combo string.
 *
 * Canonical form, on both sides of the wire: lower case, modifiers in the fixed order
 * `ctrl > shift > alt`, then the key, joined by `+` — `l`, `ctrl+m`, `ctrl+shift+enter`.
 *
 * ⚠️ **The same grammar is implemented in JavaScript**, in `keyboard_controller.js`
 * (`_comboFromEvent`) and `shortcut-capture_controller.js`. The three must agree: a combo the
 * capture screen accepts but the router cannot build is a shortcut the user configured and that
 * never fires — with nothing to explain why. `KeyboardComboNormalizerTest` pins the grammar;
 * changing it means changing all three.
 *
 * ## What is deliberately NOT accepted
 *
 * ⚠️ **`meta` (Cmd on macOS, Win elsewhere) is excluded.** Its meaning differs per platform and per
 * browser, and a combo that works on one machine and silently does nothing on another is worse
 * than no shortcut. The router treats Cmd as Ctrl where the operating system already does — in the
 * form-level accelerator — and ignores it everywhere else.
 *
 * ⚠️ **`?` and `Escape` are not combos.** They are hard-wired conventions of the router — help and
 * dismiss — and making them overridable would let a user lock themselves out of the very screen
 * that explains the shortcuts.
 *
 * ⚠️ **Arrows and `Tab` are excluded.** They move focus; stealing them breaks keyboard navigation
 * for exactly the people this feature exists for.
 */
final readonly class KeyboardComboNormalizer
{
    /** @var list<string> */
    private const array ALLOWED_MODIFIERS = ['ctrl', 'shift', 'alt'];

    /**
     * The accepted final token of a combo — letters and digits, the twelve function keys (rarely
     * claimed by a browser, and often freed by users for application binds), plus `enter` and
     * `space`, which form-level shortcuts need.
     */
    private const string KEY_PATTERN = '/^([a-z0-9]|enter|space|f([1-9]|1[0-2]))$/';

    /** The canonical form of `$combo`, or `null` when it is not a combo this system can fire. */
    public function normalize(string $combo): ?string
    {
        $parts = \array_values(\array_filter(
            \array_map(\trim(...), \explode('+', \strtolower(\trim($combo)))),
            static fn (string $part): bool => '' !== $part,
        ));

        if ([] === $parts) {
            return null;
        }

        $key = \array_pop($parts);

        if (1 !== \preg_match(self::KEY_PATTERN, $key)) {
            return null;
        }

        $modifiers = [];

        foreach ($parts as $part) {
            // ⚠️ A repeated modifier is refused rather than de-duplicated: `ctrl+ctrl+l` is not a
            // combo anyone meant to type, it is a value that came from somewhere else.
            if (!\in_array($part, self::ALLOWED_MODIFIERS, true) || \in_array($part, $modifiers, true)) {
                return null;
            }

            $modifiers[] = $part;
        }

        $ordered = \array_values(\array_filter(
            self::ALLOWED_MODIFIERS,
            static fn (string $modifier): bool => \in_array($modifier, $modifiers, true),
        ));
        $ordered[] = $key;

        return \implode('+', $ordered);
    }

    /**
     * The combos claimed by more than one action.
     *
     * ⚠️ **Reported, never resolved.** Two actions on the same combo is a configuration the user
     * made and must be told about; picking a winner here would make one of their two shortcuts
     * silently dead.
     *
     * @param array<string, string|null> $combos code → combo
     *
     * @return list<string>
     */
    public function duplicates(array $combos): array
    {
        $counts = [];

        foreach ($combos as $combo) {
            if (null === $combo || '' === $combo) {
                continue;
            }

            $counts[$combo] = ($counts[$combo] ?? 0) + 1;
        }

        return \array_values(\array_keys(\array_filter($counts, static fn (int $count): bool => $count > 1)));
    }
}
