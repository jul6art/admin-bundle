<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every shipped accent meets AA contrast on the pairs this bundle's own components render.
 *
 * ## Why this file exists, and what it replaces
 *
 * `AccentColor`'s header claimed "every value is pre-checked for AA contrast on both the light and
 * the dark background, which is why the set is closed". The claim was **asserted in a docblock and
 * enforced by nothing**, and it was measurably false: on 2026-09-08 a consumer measured
 * `text-white` on `bg-accent-600` — what `.btn-primary` renders — at **4.10 for `sky`, 3.77 for
 * `emerald`, 3.74 for `teal` and 3.19 for `amber`**. Four of seven below the 4.5 threshold, on the
 * most used control in the product.
 *
 * ⚠️ **The damage a false claim does is not the failing pixel, it is the reasoning it licenses.**
 * That consumer nearly shipped a brand ramp at 3.87 on the same pair, and the argument available to
 * it was "better than four of the seven accents the bundle ships". A claim a consumer leans on has
 * to be true or absent. The four values were darkened; this file is what keeps the sentence true.
 *
 * ## What is measured, and why it is read rather than listed
 *
 * The pairs come from `components.css` itself — `.btn-primary` (white on 600) and `.badge-accent`
 * in light theme (700 on 50). A list chosen by hand would be a list chosen to pass, and it would
 * drift the day a component changes which step it uses.
 *
 * ⚠️ **Every case in the enum is covered, including `Brand`** — and `Brand` is the one this file
 * cannot judge: the bundle deliberately ships no ramp for it, because a catalogue shared by several
 * products cannot hold the colour of one of them. It is skipped **by name**, with the reason, and
 * the consumer's own guard is what covers it (cegeta and cereezer both have one). An accent skipped
 * silently would be the same defect as a claim asserted silently.
 *
 * ⚠️ **The comments of `tokens.css` are stripped before anything is searched.** Its header carries
 * an EXAMPLE `:root { --brand-swatch: … }`, written to tell consumers what to declare — so a naive
 * reader finds that block first, reads zero steps out of it, and compares nothing to nothing. A
 * consumer's guard was born green and hollow that way, and its premise assertion is what caught it.
 */
#[CoversNothing]
final class AccentContrastTest extends TestCase
{
    /** The WCAG 2.1 threshold for normal text. */
    private const float AA = 4.5;

    /**
     * ⚠️ `Brand` carries NO ramp in this bundle, on purpose — the absence is the feature. It is the
     * consumer that declares its own colour, so it is the consumer that owes the contrast test.
     */
    private const string SANS_RAMPE = 'brand';

    /**
     * @return iterable<string, array{AccentColor}>
     */
    public static function provideAccents(): iterable
    {
        foreach (AccentColor::cases() as $accent) {
            yield $accent->value => [$accent];
        }
    }

    #[DataProvider('provideAccents')]
    public function testTheAccentMeetsAaOnEveryPairTheComponentsRender(AccentColor $accent): void
    {
        if (self::SANS_RAMPE === $accent->value) {
            self::assertSame(
                [],
                self::ramp($accent->value),
                'The bundle now ships a ramp for `brand`. That breaks the contract this case exists '
                .'to state: a catalogue shared by several products cannot hold the colour of one of '
                .'them, so `brand` is the consumer\'s to declare AND the consumer\'s to prove.',
            );

            return;
        }

        $ramp = 'indigo' === $accent->value ? self::rootRamp() : self::ramp($accent->value);

        // ⚠️ The premise: without it, an accent whose block was renamed would read as an empty ramp
        // and pass every pair below by comparing nothing.
        self::assertCount(
            10,
            $ramp,
            \sprintf('`%s` declares %d accent steps instead of 10: the survey reads the wrong block.', $accent->value, \count($ramp)),
        );

        foreach ([
            '.btn-primary — white on bg-accent-600' => [[255, 255, 255], $ramp[600]],
            '.badge-accent — text-accent-700 on bg-accent-50' => [$ramp[700], $ramp[50]],
        ] as $pair => [$front, $back]) {
            $ratio = self::contrast($front, $back);

            self::assertGreaterThanOrEqual(
                self::AA,
                $ratio,
                \sprintf(
                    "Accent `%s`, pair « %s »: contrast %.2f, below the AA threshold of %.1f.\n\n"
                    ."⚠️ `AccentColor`'s header states that every value is pre-checked for AA. "
                    ."That sentence is only true while this case passes.\n\n"
                    .'⚠️ The fix is to darken steps 600 and up and to LEAVE step 500 alone: 500 '
                    .'carries the impression of the colour (it is the hover state and the focus '
                    .'ring), while 600 is a button background half a step darker, which nobody '
                    .'reads as a departure from the palette.',
                    $accent->value,
                    $pair,
                    $ratio,
                    self::AA,
                ),
            );
        }
    }

    /**
     * ⚠️ **The pairs are READ from `components.css`, not trusted from this file's docblock.** If a
     * component changes which step it renders, the two cases above would keep measuring the old
     * pair and stay green on a regression they exist to catch.
     */
    public function testThePairsMeasuredAreStillThePairsRendered(): void
    {
        $components = self::sansCommentaires((string) \file_get_contents(
            \dirname(__DIR__, 2).'/assets/styles/components.css',
        ));

        self::assertSame(
            1,
            \preg_match('/\.btn-primary[^}]*bg-accent-600[^}]*text-white/s', $components),
            '`.btn-primary` no longer renders `text-white` on `bg-accent-600`: the pair measured '
            .'above is not the pair rendered, so the contrast is guarded on a component that no '
            .'longer exists in that form.',
        );

        self::assertSame(
            1,
            \preg_match('/\.badge-accent[^}]*bg-accent-50[^}]*text-accent-700/s', $components),
            '`.badge-accent` no longer renders `text-accent-700` on `bg-accent-50`.',
        );
    }

    /**
     * The `:root` ramp — the indigo preset, and the fallback for any unknown accent.
     *
     * @return array<int, array{int, int, int}>
     */
    private static function rootRamp(): array
    {
        return self::stepsOf('/:root\s*\{(.*?)\}/s');
    }

    /**
     * @return array<int, array{int, int, int}>
     */
    private static function ramp(string $accent): array
    {
        return self::stepsOf('/\[data-accent=\''.\preg_quote($accent, '/').'\'\]\s*\{(.*?)\}/s');
    }

    /**
     * @return array<int, array{int, int, int}>
     */
    private static function stepsOf(string $motif): array
    {
        // ⚠️ Comments first. The header of `tokens.css` carries an example `:root` block for
        // consumers, holding zero steps — a naive reader finds it before the real one.
        $tokens = self::sansCommentaires((string) \file_get_contents(
            \dirname(__DIR__, 2).'/assets/styles/tokens.css',
        ));

        if (1 !== \preg_match($motif, $tokens, $bloc)) {
            return [];
        }

        \preg_match_all('/--accent-(\d+)\s*:\s*(\d+)\s+(\d+)\s+(\d+)\s*;/', $bloc[1], $lignes, \PREG_SET_ORDER);

        $ramp = [];

        foreach ($lignes as $ligne) {
            $ramp[(int) $ligne[1]] = [(int) $ligne[2], (int) $ligne[3], (int) $ligne[4]];
        }

        return $ramp;
    }

    /**
     * @param array{int, int, int} $front
     * @param array{int, int, int} $back
     */
    private static function contrast(array $front, array $back): float
    {
        $a = self::luminance($front);
        $b = self::luminance($back);

        return (\max($a, $b) + 0.05) / (\min($a, $b) + 0.05);
    }

    /**
     * ⚠️ WCAG relative luminance, with its per-channel linearisation: averaging the three
     * components gives a number that looks like a contrast without being one.
     *
     * @param array{int, int, int} $rgb
     */
    private static function luminance(array $rgb): float
    {
        $canaux = \array_map(
            static function (int $composante): float {
                $c = $composante / 255;

                return $c <= 0.04045 ? $c / 12.92 : ((($c + 0.055) / 1.055) ** 2.4);
            },
            $rgb,
        );

        return 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];
    }

    private static function sansCommentaires(string $contenu): string
    {
        return (string) \preg_replace('!/\*.*?\*/!s', '', $contenu);
    }
}
