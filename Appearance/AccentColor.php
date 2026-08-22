<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Appearance;

/**
 * Per-user accent colour. Drives the `--accent-*` CSS custom properties through the `data-accent`
 * attribute the layout writes on `<html>`.
 *
 * Every value is pre-checked for AA contrast on both the light and the dark background, which is
 * why the set is closed: an accent picked freely is an accent that will eventually be unreadable
 * on one of the two themes, and nobody tests that.
 */
enum AccentColor: string
{
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
     */
    public function swatch(): string
    {
        return match ($this) {
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
