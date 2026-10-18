<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Ui;

/**
 * The colours of the transactional e-mail layout (`@Admin/email/base.html.twig`), so a product's
 * e-mails look like the product rather than the bundle's neutral slate.
 *
 * ```yaml
 * admin:
 *     branding:
 *         email:
 *             accent: '#1565d8'          # the button — your `--accent-600`, AA-checked with white
 *             accent_text: '#ffffff'
 * ```
 *
 * ```yaml
 * admin:
 *     branding:
 *         logo_dark: 'img/logo-white.png'
 *         email:                         # a NIGHT layout, for a product whose identity is dark
 *             background: '#0e0a1a'
 *             panel: '#151026'
 *             border: '#2a1f45'
 *             text: '#dcd0e6'
 *             heading: '#f8f1fa'
 *             muted: '#ada0c0'
 *             accent: '#ff4f9a'
 *             accent_text: '#1a0b14'
 *             logo: dark                 # serve `logo_dark` — the light wordmark
 * ```
 *
 * ⚠️ **Hard-coded hex values, never CSS custom properties**: a mail client loads no stylesheet and
 * most strip `<style>` — every colour is written inline, so it has to be a literal. That is also
 * why the values are validated as `#rrggbb`: they are interpolated into `style` attributes.
 *
 * ⚠️ **The defaults are the layout's historical colours, to the byte**: a product that declares
 * nothing sends exactly the e-mails it sent before.
 *
 * ⚠️ **Legibility is enforced, not hoped for** (the lesson of {@see \Jul6Art\AdminBundle\Appearance\AccentColor}):
 * the configuration refuses a palette whose body text on the panel, or button text on the accent,
 * falls under 4.5:1 — see {@see self::contrast()}.
 *
 * `logo: dark` serves `branding.logo_dark` (when there is one): a dark e-mail needs the light
 * wordmark, and a mail client cannot switch variants with CSS the way the shell does.
 */
final readonly class EmailPalette
{
    public const array DEFAULTS = [
        'background' => '#f1f5f9',
        'panel' => '#ffffff',
        'border' => '#e2e8f0',
        'text' => '#334155',
        'heading' => '#1e293b',
        'muted' => '#94a3b8',
        'accent' => '#1e293b',
        'accent_text' => '#ffffff',
        'logo' => 'light',
    ];

    /** The pairs that must stay legible: [foreground, background], by configuration key. */
    public const array LEGIBLE_PAIRS = [
        ['text', 'panel'],
        ['heading', 'background'],
        ['accent_text', 'accent'],
    ];

    public const float MINIMUM_CONTRAST = 4.5;

    public function __construct(
        public string $background = self::DEFAULTS['background'],
        public string $panel = self::DEFAULTS['panel'],
        public string $border = self::DEFAULTS['border'],
        public string $text = self::DEFAULTS['text'],
        public string $heading = self::DEFAULTS['heading'],
        public string $muted = self::DEFAULTS['muted'],
        public string $accent = self::DEFAULTS['accent'],
        public string $accentText = self::DEFAULTS['accent_text'],
        public string $logo = self::DEFAULTS['logo'],
    ) {
    }

    /** Whether the layout should serve the dark-theme logo variant (`branding.logo_dark`). */
    public function usesDarkLogo(): bool
    {
        return 'dark' === $this->logo;
    }

    /**
     * WCAG 2 contrast ratio between two `#rrggbb` colours, from 1 to 21.
     */
    public static function contrast(string $foreground, string $background): float
    {
        $luminance = static function (string $hex): float {
            $channels = array_map(
                static function (string $pair): float {
                    $value = hexdec($pair) / 255;

                    return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
                },
                str_split(ltrim($hex, '#'), 2),
            );

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        $lighter = max($luminance($foreground), $luminance($background));
        $darker = min($luminance($foreground), $luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
