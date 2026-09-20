<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Keyboard;

/**
 * The combo that is actually in force for an action: what the visitor chose, or what the catalogue
 * ships.
 *
 * ⚠️ **The store is optional, and its absence is not a failure.** Without one, every shortcut still
 * fires on its factory combo — see {@see KeyboardShortcutStoreInterface} for why the degradation
 * goes that way rather than the other.
 *
 * ⚠️ **An override that is not a valid combo is IGNORED, not repaired.** A stored value can predate
 * a change of grammar, or come from a hand-edited row; feeding it to the router would produce a
 * `data-shortcut` that no keystroke can ever match, and the user would see a shortcut documented in
 * the cheat-sheet that does nothing. Falling back on the default is the only outcome that keeps the
 * screen honest.
 */
final readonly class KeyboardShortcutResolver
{
    public function __construct(
        private KeyboardCatalogue $catalogue,
        private KeyboardComboNormalizer $normalizer,
        private ?KeyboardShortcutStoreInterface $store = null,
    ) {
    }

    /** The combo in force, or `''` when the code is not in the catalogue. */
    public function resolve(string $code): string
    {
        $default = $this->catalogue->defaultFor($code);

        if (null === $default) {
            // ⚠️ `''` and not an exception: this is reached from a template, through a code written
            // by hand. A typo must hide the shortcut, not break the page that carries it.
            return '';
        }

        $override = $this->store?->overrideFor($code);

        if (null === $override || '' === $override) {
            return $default;
        }

        return $this->normalizer->normalize($override) ?? $default;
    }

    /**
     * The catalogue itself — for a settings screen that lists the actions with their labels.
     *
     * @return list<KeyboardAction>
     */
    public function actions(): array
    {
        return $this->catalogue->all();
    }

    /**
     * Every action with the combo in force — what the settings screen lists and what the layout
     * hands to the browser.
     *
     * @return array<string, string> code → combo
     */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach ($this->catalogue->all() as $action) {
            $snapshot[$action->code] = $this->resolve($action->code);
        }

        return $snapshot;
    }
}
