<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Appearance;

/**
 * Per-user accent colour. Drives the `--accent-*` CSS custom properties through the `data-accent`
 * attribute the layout writes on `<html>`.
 *
 * ## What "pre-checked" means here, exactly
 *
 * ⚠️ **This paragraph used to claim more than the catalogue delivered, and the gap was measurable.**
 * It said "every value is pre-checked for AA contrast on both the light and the dark background".
 * Measured on 2026-09-08 — reported by a consumer, verified independently — `text-white` on
 * `bg-accent-600`, which is what `.btn-primary` renders, gave **4.10 for `sky`, 3.77 for
 * `emerald`, 3.74 for `teal` and 3.19 for `amber`**: four of seven below the 4.5 AA threshold, on
 * the most used control in the product.
 *
 * A consumer had relied on the sentence, and it nearly served as an excuse — "3.87 is better than
 * four of the seven shipped accents, so it is defensible". A claim a consumer leans on has to be
 * true or absent.
 *
 * The four step-600 values were therefore darkened to the real threshold, step 500 left untouched,
 * and the claim is now enforced rather than asserted: {@see \Jul6Art\AdminBundle\Tests\Unit\AccentContrastTest}
 * measures every accent on the pairs `components.css` actually renders. The set stays closed for
 * the original reason — an accent picked freely is an accent that will eventually be unreadable on
 * one of the two themes, and nobody tests that — except that now somebody does.
 *
 * ## ⚠️ `Brand` is the DEFAULT, and the bundle defines no ramp for it
 *
 * Reported by cegeta on 2026-09-08. `Indigo` used to be the default, so `data-accent` was ALWAYS
 * written on `<html>` — for every account of every product. A consumer that declared its own brand
 * ramp on `:root` therefore saw it applied nowhere: the attribute rule always won. Measured in the
 * browser, cegeta rendered indigo throughout `/app` and `/admin` next to a green logo, while its
 * brand green was alive only on its anonymous surfaces, which carry no account.
 *
 * ⚠️ The fix is not another closed colour: none of the seven is any given product's brand, and
 * adding one per product would make this enum a registry of clients. `Brand` means "whatever this
 * installation declares", and the bundle deliberately ships **no** `[data-accent='brand']` rule —
 * each product writes it. A consumer that writes none keeps today's look, because the `:root` ramp
 * of `tokens.css` remains the fallback.
 */
enum AccentColor: string
{
    /**
     * ⚠️ The product's OWN accent — see the class docblock. First in the list because it is the
     * default, and because a picker should open on "the colour of this product" rather than on one
     * of the seven alternatives.
     */
    case Brand = 'brand';

    case Indigo = 'indigo';
    case Emerald = 'emerald';
    case Rose = 'rose';
    case Amber = 'amber';
    case Sky = 'sky';
    case Violet = 'violet';
    case Teal = 'teal';

    /** Translation key, consumed by Twig and by the form's `choice_label`. */
    public function translationKey(): string
    {
        return 'appearance.accent.'.$this->value;
    }

    /**
     * The reference swatch shown in the settings form. Fixed per accent and independent of the
     * active one — a picker whose swatches follow the current choice tells the user nothing.
     *
     * ⚠️ `Brand` is the exception, and it has to be: the bundle cannot know a product's colour. It
     * returns a custom property the consumer declares once, with the bundle's own indigo as the
     * fallback — the same value the `:root` ramp falls back to, so a product that declares neither
     * stays coherent.
     *
     * ⚠️ **`--brand-swatch` and not `--accent-500`.** The swatch must be independent of the ACTIVE
     * accent: reading the live ramp would paint the brand dot in whatever colour the account
     * currently uses, so the picker would show two identical dots and tell the user nothing.
     */
    public function swatch(): string
    {
        return match ($this) {
            self::Brand => 'var(--brand-swatch, #6366f1)',
            self::Indigo => '#6366f1',
            self::Emerald => '#10b981',
            self::Rose => '#f43f5e',
            self::Amber => '#f59e0b',
            self::Sky => '#0ea5e9',
            self::Violet => '#8b5cf6',
            self::Teal => '#0d9488',
        };
    }
}
