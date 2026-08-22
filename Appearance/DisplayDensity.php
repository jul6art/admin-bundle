<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Appearance;

/**
 * Per-user display density, in the Gmail sense. Drives the `data-density` attribute on `<html>`,
 * which retunes datatable row height and panel padding through two CSS variables.
 */
enum DisplayDensity: string
{
    case Comfortable = 'comfortable';
    case Cozy = 'cozy';
    case Compact = 'compact';

    /** Translation key, consumed by Twig and by the form's `choice_label`. */
    public function translationKey(): string
    {
        return 'appearance.density.'.$this->value;
    }
}
