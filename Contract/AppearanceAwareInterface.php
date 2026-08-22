<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\Appearance\FontScale;

/**
 * What the layout and the settings form need to know about the signed-in account — and nothing
 * else. No name, no e-mail, no roles: those belong to the application's own `User`.
 *
 * {@see \Jul6Art\AdminBundle\Entity\Traits\AppearancePreferencesTrait} implements all of it except
 * the colour mode, which almost every application already stores under its own name.
 *
 * ```php
 * #[ORM\Entity]
 * class User implements AppearanceAwareInterface
 * {
 *     use AppearancePreferencesTrait;
 *
 *     #[ORM\Column(length: 10, options: ['default' => 'light'])]
 *     private string $theme = 'light';
 *
 *     public function getColorMode(): ColorMode
 *     {
 *         return ColorMode::fromStorage($this->theme);
 *     }
 *
 *     public function setColorMode(ColorMode $mode): static
 *     {
 *         $this->theme = $mode->value;
 *
 *         return $this;
 *     }
 * }
 * ```
 */
interface AppearanceAwareInterface
{
    public function getColorMode(): ColorMode;

    public function setColorMode(ColorMode $mode): static;

    public function getAccent(): AccentColor;

    public function setAccent(AccentColor $accent): static;

    public function getDensity(): DisplayDensity;

    public function setDensity(DisplayDensity $density): static;

    public function getFontScale(): FontScale;

    public function setFontScale(FontScale $fontScale): static;

    public function isHighContrast(): bool;

    public function setHighContrast(bool $highContrast): static;

    public function isReducedMotion(): bool;

    public function setReducedMotion(bool $reducedMotion): static;
}
