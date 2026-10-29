<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * No template of the bundle compiles with a deprecation.
 *
 * Twig deprecates what its next major will read differently — `item => item.condition ?? true`, whose
 * `??` will bind tighter than the arrow — and says so only when the template compiles, in the
 * application that renders it: three of them surfaced in `debug:container --deprecations` of every
 * product (2026-10-29), none here. Each template is parsed as Twig reads it, the functions, filters
 * and tests of the other bundles stubbed, since only the parse raises these deprecations.
 */
#[CoversNothing]
final class TemplateDeprecationTest extends TestCase
{
    #[DataProvider('templates')]
    public function testTheTemplateCompilesWithoutADeprecation(string $name): void
    {
        $twig = new Environment(new FilesystemLoader(self::views()), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension());
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));
        $twig->registerUndefinedFilterCallback(static fn (string $name): TwigFilter => new TwigFilter($name, static fn (mixed $value): mixed => $value));
        $twig->registerUndefinedTestCallback(static fn (string $name): TwigTest => new TwigTest($name, static fn (): bool => true));

        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $twig->parse($twig->tokenize(new Source((string) file_get_contents(self::views().'/'.$name), $name)));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::views(), \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.twig')) {
                $name = substr($file->getPathname(), \strlen(self::views()) + 1);

                yield $name => [$name];
            }
        }
    }

    private static function views(): string
    {
        return \dirname(__DIR__, 2).'/Resources/views';
    }
}
