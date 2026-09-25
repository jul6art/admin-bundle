<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The decimal-input controller the ui-bundle's decimal types rely on.
 *
 * ⚠️ `CustomMoneyType` and `CustomUnitType` attach `form--decimal` to their input and ship NO
 * controller. For months a consumer project (cereezer) had none either: seven price, quantity and
 * rate fields showed "89.00", and nothing failed — a Stimulus identifier that resolves to nothing
 * raises nothing. The controller moved here from `superp` (2026-09-25) so every consumer gets the
 * same one.
 *
 * Same reasoning as {@see KeyboardBehaviourSourceTest}: no JavaScript runner in this bundle, so
 * what these cases pin is that three decisions are still MADE in the file.
 */
#[CoversNothing]
final class DecimalBehaviourSourceTest extends TestCase
{
    /**
     * `parse()` is PUBLIC API: superp's line-total and VAT-total controllers import the class and
     * call it statically. Renaming it or making it an instance method breaks them without a trace.
     */
    public function testParseStaysAStaticMethod(): void
    {
        self::assertMatchesRegularExpression('/^\s{4}static parse\(str\)/m', self::read());
    }

    /**
     * A `type="number"` input cannot hold "3 600,00": the browser empties it silently. A field
     * rendered with `html5: true` must be switched to text, or the controller erases its value.
     */
    public function testANumberInputIsSwitchedToText(): void
    {
        $source = self::read();

        self::assertStringContainsString("this.element.type === 'number'", $source);
        self::assertStringContainsString("this.element.type = 'text'", $source);
        self::assertStringContainsString("this.element.inputMode = 'decimal'", $source);
    }

    /** The server parses a plain dot value whatever its locale: the submit handler restores it. */
    public function testTheSubmittedValueIsPlain(): void
    {
        $source = self::read();

        self::assertMatchesRegularExpression("/addEventListener\\('submit', this\\._onSubmit\\)/", $source);
        self::assertMatchesRegularExpression('/_onSubmit\(\) \{\s*this\._plain\(\);/', $source);
    }

    private static function read(): string
    {
        $path = \dirname(__DIR__, 2).'/assets/controllers/decimal_controller.js';

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
