<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every `.btn-*`, `.badge-*` and `.alert-*` of `components.css` holds AA contrast between its text
 * and its background, in both themes and in every state it declares a colour for.
 *
 * ## Why this file exists
 *
 * `AccentContrastTest` guarded the accent — the colour a USER picks — and nothing guarded the
 * colours the bundle picks itself. On 2026-09-23 a consumer's review measured `.btn-warning`, white
 * on `amber-500`, at **2.15**; its hover (`amber-400`) was **1.67**. The same survey found
 * `.btn-success` at 3.77 (hover 2.54), the hover of `.btn-danger` at 3.76 and `.badge-inactive` at
 * 4.34 in light and 4.04 in dark. Five controls below 4.5, shipped to three products.
 *
 * ## What is measured, and why it is READ
 *
 * The rules and their pairs come from `components.css` itself: every rule of the three families
 * whose body is an `@apply`, resolved the way Tailwind resolves it — per theme (`dark:`), per state
 * (`hover:`, `focus:`, `disabled:`… whatever the rule declares), a translucent background
 * composited over the surfaces it actually sits on (the page and `.panel`, read from the shell's
 * `base.html.twig` and from this sheet). A list of pairs written here would be a list chosen to
 * pass, and it would keep measuring the old step the day a rule changes.
 *
 * ⚠️ **The palette is the one thing this file holds**, because it is not the bundle's: it is
 * Tailwind 3.4's default palette (`tailwindcss/lib/public/colors.js`, frozen with the v3 line),
 * which the consumer's build resolves. It is not a list of pairs — it is the dictionary the pairs
 * are looked up in — and a token this dictionary does not know FAILS rather than being skipped:
 * a hue added to a rule without being added here would otherwise be a rule measured by nothing.
 *
 * ⚠️ `.btn-primary` and `.badge-accent` are skipped **by name**: their colour is the accent, which
 * has seven ramps, and `AccentContrastTest` measures them per accent. A rule skipped silently would
 * be the same defect as a claim asserted silently, so the skipped set is asserted too.
 */
#[CoversNothing]
final class SemanticContrastTest extends TestCase
{
    /** The WCAG 2.1 threshold for normal text — every label of these families is 12 to 14 px. */
    private const float AA = 4.5;

    /** The rules whose colour is the accent: `AccentContrastTest` measures them, per accent. */
    private const array ACCENT_RULES = ['badge-accent', 'btn-primary'];

    /**
     * Tailwind 3.4's default palette, for the hues this sheet uses — see the class docblock.
     *
     * @var array<string, array<int, string>>
     */
    private const array PALETTE = [
        'slate' => [50 => '#f8fafc', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1', 400 => '#94a3b8', 500 => '#64748b', 600 => '#475569', 700 => '#334155', 800 => '#1e293b', 900 => '#0f172a', 950 => '#020617'],
        'red' => [50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5', 400 => '#f87171', 500 => '#ef4444', 600 => '#dc2626', 700 => '#b91c1c', 800 => '#991b1b', 900 => '#7f1d1d', 950 => '#450a0a'],
        'amber' => [50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d', 400 => '#fbbf24', 500 => '#f59e0b', 600 => '#d97706', 700 => '#b45309', 800 => '#92400e', 900 => '#78350f', 950 => '#451a03'],
        'emerald' => [50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399', 500 => '#10b981', 600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b', 950 => '#022c22'],
        'sky' => [50 => '#f0f9ff', 100 => '#e0f2fe', 200 => '#bae6fd', 300 => '#7dd3fc', 400 => '#38bdf8', 500 => '#0ea5e9', 600 => '#0284c7', 700 => '#0369a1', 800 => '#075985', 900 => '#0c4a6e', 950 => '#082f49'],
        'violet' => [50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd', 400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#7c3aed', 700 => '#6d28d9', 800 => '#5b21b6', 900 => '#4c1d95', 950 => '#2e1065'],
    ];

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRules(): iterable
    {
        foreach (self::rules() as $rule => $tokens) {
            if (\in_array($rule, self::ACCENT_RULES, true)) {
                continue;
            }

            yield $rule => [$rule, $tokens];
        }
    }

    /**
     * @param list<string> $tokens
     */
    #[DataProvider('provideRules')]
    public function testTheRuleMeetsAaInEveryThemeAndState(string $rule, array $tokens): void
    {
        $pairs = self::pairs($tokens);

        // ⚠️ The premise: a rule whose colours the reader failed to find would pass by measuring
        // nothing. Both themes, at least, must yield a pair.
        self::assertGreaterThanOrEqual(2, \count($pairs), \sprintf('`.%s`: fewer than two pairs read — the survey does not see its colours.', $rule));

        $failures = [];

        foreach ($pairs as $label => [$front, $back]) {
            $ratio = self::contrast($front, $back);

            if ($ratio < self::AA) {
                $failures[] = \sprintf('  %s: %.2f', $label, $ratio);
            }
        }

        self::assertSame([], $failures, \sprintf(
            "`.%s` is below the AA threshold of %.1f:\n%s\n\n"
            .'⚠️ Fix it with the step or the ink, not with the hue: a warning that stops looking '
            .'amber no longer reads as a warning. And a hover is read too — a white label whose '
            .'resting step is the lightest that passes has to hover DARKER.',
            $rule,
            self::AA,
            implode("\n", $failures),
        ));
    }

    /**
     * ⚠️ The survey sees the family, and skips only what it says it skips. Without this, a regex
     * that stopped matching would turn the provider above into an empty list — and an empty data
     * provider is a warning, not a guarantee.
     */
    public function testTheSurveyCoversTheFamilyAndSkipsOnlyTheAccent(): void
    {
        $rules = array_keys(self::rules());

        foreach (['btn-danger', 'btn-warning', 'btn-success', 'badge-warning', 'alert-warning'] as $expected) {
            self::assertContains($expected, $rules, \sprintf('`.%s` is not read out of `components.css`: the survey reads the wrong rules.', $expected));
        }

        $accent = array_values(array_filter(
            $rules,
            static fn (string $rule): bool => (bool) preg_match('/(?:^|\s)(?:[a-z-]+:)*(?:bg|text)-accent-/', implode(' ', self::rules()[$rule])),
        ));
        sort($accent);

        self::assertSame(self::ACCENT_RULES, $accent, 'The rules painted with the accent are not the ones skipped by name: '
            .'a new one would be measured by nothing, or a skipped one no longer uses the accent.');
    }

    /**
     * The `@apply` rules of the three families, keyed by class name.
     *
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        preg_match_all(
            '/\.((?:btn|badge|alert)-[a-z][a-z0-9-]*)\s*\{\s*@apply\s+([^;]+);/',
            self::components(),
            $matches,
            \PREG_SET_ORDER,
        );

        $rules = [];

        foreach ($matches as $match) {
            $rules[$match[1]] = preg_split('/\s+/', trim($match[2]), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return $rules;
    }

    /**
     * Every (theme, state, surface) the rule renders, as text colour over background colour.
     *
     * @param list<string> $tokens
     *
     * @return array<string, array{array{int, int, int}, array{int, int, int}}>
     */
    private static function pairs(array $tokens): array
    {
        $colours = self::colours($tokens);
        $states = [''];

        foreach (array_keys($colours) as $key) {
            $state = self::withoutDark($key);

            if ('' !== $state && !\in_array($state, $states, true)) {
                $states[] = $state;
            }
        }

        $surfaces = self::surfaces();
        $pairs = [];

        foreach (['light', 'dark'] as $theme) {
            foreach ($states as $state) {
                $text = self::resolve($colours, 'text', $theme, $state);
                $background = self::resolve($colours, 'bg', $theme, $state);

                if (null === $text) {
                    continue;
                }

                $label = \sprintf('%s %s', $theme, '' === $state ? 'rest' : $state);

                // An opaque background does not depend on what it sits on: one pair says it all.
                if (null !== $background && 1.0 === $background[1]) {
                    $pairs[$label] = [self::over($text, $background[0]), $background[0]];

                    continue;
                }

                foreach ($surfaces[$theme] as $surfaceName => $surface) {
                    $back = null === $background ? $surface : self::over($background, $surface);
                    $pairs[$label.', over the '.$surfaceName] = [self::over($text, $back), $back];
                }
            }
        }

        return $pairs;
    }

    /**
     * The colour utilities of a rule, keyed by `kind|variants` (`bg|dark:hover`).
     *
     * @param list<string> $tokens
     *
     * @return array<string, array{array{int, int, int}, float}>
     */
    private static function colours(array $tokens): array
    {
        $colours = [];

        foreach ($tokens as $token) {
            $parts = explode(':', $token);
            $utility = array_pop($parts);

            if (1 !== preg_match('/^(bg|text)-(white|black|[a-z]+-\d{2,3})(?:\/(\d{1,3}))?$/', $utility, $match)) {
                continue;
            }

            sort($parts);
            $colours[$match[1].'|'.implode(':', $parts)] = [
                self::colour($match[2]),
                isset($match[3]) ? (int) $match[3] / 100 : 1.0,
            ];
        }

        return $colours;
    }

    /**
     * The colour a state renders, in the cascade order Tailwind 3 generates: `dark:` is emitted
     * after the pseudo-class variants, so in the dark theme `dark:bg-*` wins over a bare `hover:`.
     *
     * @param array<string, array{array{int, int, int}, float}> $colours
     *
     * @return array{array{int, int, int}, float}|null
     */
    private static function resolve(array $colours, string $kind, string $theme, string $state): ?array
    {
        $withDark = static function (string $state): string {
            $variants = array_filter(['dark', ...explode(':', $state)], static fn (string $v): bool => '' !== $v);
            sort($variants);

            return implode(':', $variants);
        };

        $candidates = 'dark' === $theme
            ? [$withDark($state), 'dark', $state, '']
            : [$state, ''];

        foreach ($candidates as $variants) {
            if (isset($colours[$kind.'|'.$variants])) {
                return $colours[$kind.'|'.$variants];
            }
        }

        return null;
    }

    private static function withoutDark(string $key): string
    {
        $variants = array_filter(
            explode(':', explode('|', $key)[1] ?? ''),
            static fn (string $v): bool => '' !== $v && 'dark' !== $v,
        );

        return implode(':', $variants);
    }

    /**
     * The page and the panel, per theme: what a translucent badge is composited over.
     *
     * @return array{light: array<string, array{int, int, int}>, dark: array<string, array{int, int, int}>}
     */
    private static function surfaces(): array
    {
        $base = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/base.html.twig');
        self::assertSame(1, preg_match('/<body class="([^"]+)"/', $base, $body), 'The shell\'s <body> class is not found: the page surface is unknown.');

        self::assertSame(1, preg_match('/\.panel\s*\{\s*@apply\s+([^;]+);/', self::components(), $panelRule), '`.panel` is not found: the card surface is unknown.');

        $surfaces = ['light' => [], 'dark' => []];

        foreach (['page' => $body[1], 'panel' => $panelRule[1]] as $name => $classes) {
            $colours = self::colours(preg_split('/\s+/', trim($classes), -1, \PREG_SPLIT_NO_EMPTY) ?: []);

            foreach (['light' => 'bg|', 'dark' => 'bg|dark'] as $theme => $key) {
                self::assertArrayHasKey($key, $colours, \sprintf('The %s surface declares no %s background.', $name, $theme));
                $surfaces[$theme][$name] = $colours[$key][0];
            }
        }

        return $surfaces;
    }

    /**
     * @return array{int, int, int}
     */
    private static function colour(string $name): array
    {
        $hex = match ($name) {
            'white' => '#ffffff',
            'black' => '#000000',
            default => (static function (string $name): string {
                [$hue, $step] = explode('-', $name);

                self::assertArrayHasKey($hue, self::PALETTE, \sprintf(
                    '`%s` is a hue this guard cannot look up. Add its Tailwind 3.4 ramp to PALETTE — '
                    .'skipping it would leave the rule measured by nothing.',
                    $name,
                ));

                return self::PALETTE[$hue][(int) $step] ?? self::fail(\sprintf('`%s` is not a step of the palette.', $name));
            })($name),
        };

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /**
     * @param array{array{int, int, int}, float} $colour
     * @param array{int, int, int}               $under
     *
     * @return array{int, int, int}
     */
    private static function over(array $colour, array $under): array
    {
        [$rgb, $alpha] = $colour;

        return [
            (int) round($rgb[0] * $alpha + $under[0] * (1 - $alpha)),
            (int) round($rgb[1] * $alpha + $under[1] * (1 - $alpha)),
            (int) round($rgb[2] * $alpha + $under[2] * (1 - $alpha)),
        ];
    }

    /**
     * @param array{int, int, int} $front
     * @param array{int, int, int} $back
     */
    private static function contrast(array $front, array $back): float
    {
        $a = self::luminance($front);
        $b = self::luminance($back);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * ⚠️ WCAG relative luminance, with its per-channel linearisation.
     *
     * @param array{int, int, int} $rgb
     */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(
            static function (int $component): float {
                $c = $component / 255;

                return $c <= 0.04045 ? $c / 12.92 : ((($c + 0.055) / 1.055) ** 2.4);
            },
            $rgb,
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function components(): string
    {
        return (string) preg_replace('!/\*.*?\*/!s', '', (string) file_get_contents(
            \dirname(__DIR__, 2).'/assets/styles/components.css',
        ));
    }
}
