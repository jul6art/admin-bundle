<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\Appearance\FontScale;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The five appearance columns, on the application's own `User`.
 *
 * ```php
 * #[ORM\Entity]
 * class User implements AppearanceAwareInterface
 * {
 *     use AppearancePreferencesTrait;
 *     // … plus getColorMode() / setColorMode(), which map onto whatever column already holds it
 * }
 * ```
 *
 * ## Why a trait rather than an embeddable
 *
 * An `#[ORM\Embeddable]` shipped by a bundle needs a Doctrine mapping entry for the bundle's
 * namespace, and in this ecosystem those mappings are deliberately switched off — one of them
 * would otherwise map a vendor `User` and create a second `user` table. A trait needs nothing:
 * Doctrine reads the attributes on the entity that uses it, which is how `IdTrait` and
 * `TimestampableTrait` already work on a hundred entities here.
 *
 * The columns are named `appearance_*` explicitly, which is also what a `columnPrefix: false`
 * embeddable produced — so an application migrating from one to the other has **no schema change**
 * and no migration, only accessors that move up one level.
 *
 * ## Defaults are declared twice, on purpose
 *
 * Once in PHP (the property initialiser, for a `new User()`) and once in `options['default']` (for
 * a row inserted by anything else, and for `doctrine:schema:validate` to stay in sync). Dropping
 * either one is what makes a schema drift that only shows up months later.
 */
trait AppearancePreferencesTrait
{
    /**
     * ⚠️ **`Brand` and not `Indigo`, and this changed on 2026-09-08.**.
     *
     * The old default meant `data-accent` was ALWAYS written on `<html>`, for every account of
     * every product — so a consumer's own brand ramp declared on `:root` applied nowhere, the
     * attribute rule always winning. cegeta measured it: indigo throughout `/app` and `/admin`,
     * next to a green logo.
     *
     * ⚠️ The SQL default moves with it, and it has to: the column default is what a row inserted by
     * anything other than this ORM gets, and leaving it at `'indigo'` would make new rows disagree
     * with new objects — a divergence that only shows up in the one product that imports data.
     *
     * ⚠️ **Existing rows are NOT migrated by this bundle**, and that is a decision each consumer
     * owns. Nobody CHOSE indigo — it was imposed — but nothing distinguishes "chose it" from "never
     * opened the screen", so a blanket update would silently overwrite a real preference. The
     * consumers that want it write the one-line migration themselves.
     */
    #[ORM\Column(name: 'appearance_accent', length: 16, enumType: AccentColor::class, options: ['default' => 'brand'])]
    #[Assert\NotNull]
    private AccentColor $accent = AccentColor::Brand;

    #[ORM\Column(name: 'appearance_density', length: 16, enumType: DisplayDensity::class, options: ['default' => 'comfortable'])]
    #[Assert\NotNull]
    private DisplayDensity $density = DisplayDensity::Comfortable;

    #[ORM\Column(name: 'appearance_font_scale', length: 4, enumType: FontScale::class, options: ['default' => 'md'])]
    #[Assert\NotNull]
    private FontScale $fontScale = FontScale::Md;

    #[ORM\Column(name: 'appearance_high_contrast', options: ['default' => false])]
    private bool $highContrast = false;

    #[ORM\Column(name: 'appearance_reduced_motion', options: ['default' => false])]
    private bool $reducedMotion = false;

    public function getAccent(): AccentColor
    {
        return $this->accent;
    }

    public function setAccent(AccentColor $accent): static
    {
        $this->accent = $accent;

        return $this;
    }

    public function getDensity(): DisplayDensity
    {
        return $this->density;
    }

    public function setDensity(DisplayDensity $density): static
    {
        $this->density = $density;

        return $this;
    }

    public function getFontScale(): FontScale
    {
        return $this->fontScale;
    }

    public function setFontScale(FontScale $fontScale): static
    {
        $this->fontScale = $fontScale;

        return $this;
    }

    public function isHighContrast(): bool
    {
        return $this->highContrast;
    }

    public function setHighContrast(bool $highContrast): static
    {
        $this->highContrast = $highContrast;

        return $this;
    }

    public function isReducedMotion(): bool
    {
        return $this->reducedMotion;
    }

    public function setReducedMotion(bool $reducedMotion): static
    {
        $this->reducedMotion = $reducedMotion;

        return $this;
    }
}
