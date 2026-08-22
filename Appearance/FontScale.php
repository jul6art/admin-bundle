<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Appearance;

/**
 * Per-user root font scale. Drives the `data-font` attribute on `<html>`, which sets the root
 * `font-size`; the interface being sized in `rem`, everything scales with it.
 */
enum FontScale: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';

    /** Translation key, consumed by Twig and by the form's `choice_label`. */
    public function translationKey(): string
    {
        return 'appearance.font_scale.'.$this->value;
    }
}
