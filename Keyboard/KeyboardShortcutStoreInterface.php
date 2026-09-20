<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Keyboard;

/**
 * Where an application keeps the shortcuts its users have customised.
 *
 * ## The half the bundle deliberately does not own
 *
 * This bundle knows how to ROUTE a keystroke and how to DOCUMENT it; it has no idea whether a
 * preference belongs to an account, to an organisation, or to a product-wide setting — and it has
 * no schema of its own to put one in. That half is the application's, exactly as
 * `DatatablePreferenceStoreInterface` is in `jul6art/datatable-bundle`.
 *
 * ## ⚠️ Not implementing this interface is a supported state
 *
 * An application that declares no store gets the catalogue's DEFAULTS, and every shortcut works.
 * What disappears without a store is CUSTOMISATION, never the feature.
 *
 * ⚠️ This is a deliberate departure from the datatable bundle's preference port, whose absence
 * removes the controller and makes the feature silently not exist — a trap that cost this
 * ecosystem a diagnosis. Here, the degradation is toward « the factory shortcuts work », because a
 * back-office that swallows `Ctrl+Enter` because nobody wired a database is indistinguishable from
 * one that is broken.
 */
interface KeyboardShortcutStoreInterface
{
    /**
     * The combo this visitor has chosen for `$code`, or `null` to keep the catalogue's default.
     *
     * ⚠️ **Return `null` rather than the default when in doubt.** The resolver falls back on its
     * own, and a store that echoes defaults makes it impossible to tell « the user chose this » from
     * « nobody chose anything » — which is precisely what the settings screen needs to display.
     *
     * ⚠️ The visitor is the store's business, not the caller's: it reads the security token itself,
     * exactly as the datatable preference store does. Passing a user here would force every Twig
     * call site to plumb one.
     */
    public function overrideFor(string $code): ?string;
}
