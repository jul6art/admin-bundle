<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The ⋯ menu of a record's header (`ui--dropdown`, 1.35.1) stays inside what shows it.
 *
 * It hangs from its button's right edge, for a header whose actions sit on the right. In a list's
 * preview sheet (28rem) or on a phone the actions wrap under the title, the button can stand on the
 * left, and the menu went out of the sheet by its left side — its labels cut, nothing failing.
 *
 * Same reasoning as {@see KeyboardBehaviourSourceTest}: no JavaScript runner in this bundle, so
 * what these cases pin is that the decisions are still MADE in the file.
 */
#[CoversNothing]
final class DropdownAlignmentSourceTest extends TestCase
{
    public function testTheMenuIsAlignedEachTimeItOpens(): void
    {
        self::assertMatchesRegularExpression('/if \(open\) this\._align\(\);/', self::read());
    }

    /**
     * Crossing the left edge of its first clipping ancestor (the sheet) or of the window, it hangs
     * from the button's left edge instead — and starts from the right edge again at each opening.
     */
    public function testAMenuCrossingTheLeftEdgeHangsFromTheLeft(): void
    {
        $source = self::read();

        self::assertStringContainsString("menu.style.left = '';", $source, 'Each opening measures from the right edge again.');
        self::assertStringContainsString('menu.getBoundingClientRect().left < this._clipLeft()', $source);
        self::assertStringContainsString("menu.style.left = '0';", $source);
        self::assertStringContainsString("getComputedStyle(node).overflowX !== 'visible'", $source, 'The sheet clips: its edge, not the window\'s.');
    }

    /** A class set from JavaScript alone is one Tailwind never reads in a template: no flip class. */
    public function testTheFlipIsAnInlineStyleNotAClass(): void
    {
        self::assertDoesNotMatchRegularExpression('/classList\.(add|toggle)\([\'"]admin-dropdown__menu--/', self::read());
    }

    private static function read(): string
    {
        $path = \dirname(__DIR__, 2).'/assets/controllers/dropdown_controller.js';

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
