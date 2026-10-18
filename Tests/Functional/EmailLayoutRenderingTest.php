<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The e-mail layout follows `branding.email` (`EmailPalette`): the historical slate when a product
 * declares nothing, its own colours — and its light wordmark on a dark mail — when it does.
 */
#[CoversNothing]
final class EmailLayoutRenderingTest extends AbstractFunctionalTestCase
{
    private const string MAIL = "{% extends '@Admin/email/base.html.twig' %}{% block email_body %}<p>Hello</p>{{ include('@Admin/email/_button.html.twig', {url: 'https://example.test/reset', label: 'Reset'}) }}{% endblock %}";

    public function testWithoutAPaletteTheMailKeepsItsHistoricalColours(): void
    {
        $html = $this->render([]);

        self::assertStringContainsString('background-color: #f1f5f9', $html);
        self::assertStringContainsString('background-color: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e2e8f0;', $html);
        self::assertStringContainsString('color: #334155;', $html);
        self::assertMatchesRegularExpression('#<td style="border-radius: 8px; background-color: \#1e293b;">\s*<a href="https://example.test/reset" style="[^"]*color: \#ffffff;#', $html);
    }

    public function testAProductPaletteColoursTheMail(): void
    {
        $html = $this->render(['branding' => ['email' => ['accent' => '#1565d8', 'accent_text' => '#ffffff']]]);

        self::assertStringContainsString('background-color: #1565d8;', $html, 'Le bouton prend l\'accent du produit.');
        self::assertStringContainsString('background-color: #f1f5f9', $html, 'Le reste garde ses défauts.');
    }

    /** Un e-mail de nuit sert le logo clair (`logo_dark`), seul : un client mail ne bascule pas par CSS. */
    public function testANightMailServesTheLightWordmark(): void
    {
        $html = $this->render(['branding' => [
            'name' => 'Night',
            'logo' => 'img/logo-ink.png',
            'logo_dark' => 'img/logo-white.png',
            'email' => [
                'background' => '#0e0a1a', 'panel' => '#151026', 'border' => '#2a1f45', 'text' => '#dcd0e6',
                'heading' => '#f8f1fa', 'muted' => '#ada0c0', 'accent' => '#ff4f9a', 'accent_text' => '#1a0b14', 'logo' => 'dark',
            ],
        ]]);

        self::assertStringContainsString('logo-white.png', $html);
        self::assertStringNotContainsString('logo-ink.png', $html);
        self::assertStringContainsString('background-color: #0e0a1a', $html);
        self::assertStringContainsString('color: #1a0b14;', $html);
    }

    /** Sans `logo_dark`, `logo: dark` garde le logo principal plutôt qu'une image vide. */
    public function testADarkPaletteWithoutADarkLogoKeepsTheMainOne(): void
    {
        $html = $this->render(['branding' => ['logo' => 'img/logo.png', 'email' => [
            'background' => '#0e0a1a', 'panel' => '#151026', 'text' => '#dcd0e6', 'heading' => '#f8f1fa', 'logo' => 'dark',
        ]]]);

        self::assertStringContainsString('img/logo.png', $html);
    }

    /** @param array<string, mixed> $config */
    private function render(array $config): string
    {
        $container = $this->boot(bundleConfig: $config);

        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push(Request::create('http://localhost/'));

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate(self::MAIL)->render();
    }
}
