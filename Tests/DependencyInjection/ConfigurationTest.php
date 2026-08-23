<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\DependencyInjection;

use Jul6Art\AdminBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The configuration tree is public API: an application writes against it and a rename breaks
 * someone's deployment. Assert the **whole** processed shape rather than one key at a time — that
 * is what makes an accidental addition or a changed default visible in a diff.
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsTheBundleAlias(): void
    {
        self::assertSame('admin', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    /**
     * Un logo qui embarque déjà le nom du produit (un wordmark) rend le nom en dessous redondant,
     * et sa largeur naturelle dépend du fichier : les deux se règlent en configuration, pas en
     * surchargeant la carte d'authentification.
     */
    public function testTheAuthCardBrandingIsTunable(): void
    {
        $config = self::process([['branding' => ['logo_width' => 220, 'show_name' => false]]]);

        $branding = $config['branding'];
        self::assertIsArray($branding);
        self::assertSame(220, $branding['logo_width']);
        self::assertFalse($branding['show_name']);
    }

    public function testALogoWidthMustBePositive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process([['branding' => ['logo_width' => 0]]]);
    }

    public function testItAppliesItsDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'base_template' => '@Admin/base.html.twig',
            'layout_template' => '@Admin/layout.html.twig',
            'branding' => [
                'name' => 'Admin',
                'logo' => '',
                'favicon' => '',
                'home_route' => 'admin_dashboard',
                'logo_width' => null,
                'show_name' => true,
            ],
            // Les noms par défaut sont ceux que les contrôleurs de ce bundle déclarent ; les autres
            // sont vides, et une route vide MASQUE le lien au lieu de faire échouer le rendu.
            'routes' => [
                'login' => 'admin_security_login',
                'performance' => '',
                'logout' => 'admin_security_logout',
                'register' => '',
                'reset_password_request' => '',
                'profile' => '',
                'change_password' => '',
                'appearance' => 'admin_account_appearance_edit',
                'privacy' => '',
            ],
            'mercure' => [
                'hub_url' => '',
                'token_route' => '',
            ],
        ], $this->process([]));
    }

    public function testLaterConfigsOverrideEarlierOnes(): void
    {
        self::assertTrue($this->process([['enabled' => false], ['enabled' => true]])['enabled']);
    }

    public function testBrandingIsPartiallyOverridable(): void
    {
        $branding = $this->process([['branding' => ['name' => 'Acme']]])['branding'];
        self::assertIsArray($branding);

        self::assertSame('Acme', $branding['name']);
        self::assertSame('admin_dashboard', $branding['home_route'], 'Un nœud à clés nommées se fusionne : déclarer une clé garde les autres.');
    }

    /**
     * A `booleanNode` refuses anything but a boolean, which is what you want — and the reason an
     * env var cannot gate service registration.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanValues(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['enabled' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [0];
        yield 'array' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
