<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Appearance;

/**
 * Light, dark, or whatever the operating system says.
 *
 * Stored as a plain string rather than mapped as an enum column, because applications that predate
 * this bundle already have a `theme` column holding exactly these three values — and a migration
 * that changes a column type to adopt a bundle is a migration nobody runs.
 */
enum ColorMode: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function translationKey(): string
    {
        return 'appearance.mode.'.$this->value;
    }

    /**
     * Reads a stored value, falling back to light rather than throwing.
     *
     * A user's colour preference is not worth a 500: an unknown value means the column was written
     * before the set was closed, or by hand.
     */
    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Light;
    }
}
