<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Keyboard;

/**
 * One overridable keyboard action: a stable code, the combo it ships with, and the label the
 * cheat-sheet and the settings screen show for it.
 *
 * ## Why a code and not a route
 *
 * A shortcut is bound to an INTENTION, not to a page. `global.new` fires on every index screen of
 * the back-office, and each of them decides for itself which button carries it — by writing
 * `data-shortcut` on it. Binding to routes would make the catalogue grow with the application and
 * force every project to re-declare what is the same gesture everywhere.
 */
final readonly class KeyboardAction
{
    public function __construct(
        /** The stable identifier, dotted: `global.new`, `form.save`, `erp.lines.add`. */
        public string $code,
        /** The combo this action ships with, in canonical form (`ctrl+shift+enter`). */
        public string $defaultCombo,
        /** The translation key of its human label, read by the cheat-sheet and the settings form. */
        public string $labelKey,
    ) {
    }
}
