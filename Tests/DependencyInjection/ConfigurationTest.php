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

    /**
     * Un null EXPLICITE est accepté, et c'est le cœur du correctif.
     *
     * `integerNode()->defaultNull()` refuse `logo_width: ~` : le défaut ne s'applique qu'en
     * l'ABSENCE de la clé. Or l'`info` du nœud invite précisément à ce null — et le mode
     * `backoffice` du `symfony-skeleton-generator` l'écrivait, donc aucun projet généré ne bootait
     * (signalé le 2026-08-24). Un nœud dont le défaut est null doit accepter un null explicite.
     */
    public function testAnExplicitNullLogoWidthIsAccepted(): void
    {
        $branding = self::process([['branding' => ['logo_width' => null]]])['branding'];

        self::assertIsArray($branding);
        self::assertNull($branding['logo_width']);
    }

    /**
     * La contrepartie de la nullabilité : le nœud n'étant plus un `integerNode`, c'est le
     * validateur qui refuse ce qui n'est pas un entier. Sans ce test, relâcher le type passerait
     * inaperçu — une largeur en chaîne se retrouverait telle quelle dans un attribut `style`.
     */
    public function testALogoWidthMustBeAnInteger(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process([['branding' => ['logo_width' => '220']]]);
    }

    /**
     * Les deux décisions du projet — PROPOSER le second facteur, et l'IMPOSER — et la seule
     * combinaison absurde : l'imposer sans le proposer.
     */
    public function testTwoFactorIsOffUnlessTheApplicationOffersIt(): void
    {
        $twoFactor = self::process([['two_factor' => ['enabled' => true, 'required' => true, 'exempt_routes' => ['dev_login_link_check']]]])['two_factor'];

        self::assertIsArray($twoFactor);
        self::assertTrue($twoFactor['enabled']);
        self::assertTrue($twoFactor['required']);
        self::assertSame(['dev_login_link_check'], $twoFactor['exempt_routes']);
    }

    public function testTwoFactorCannotBeRequiredWithoutBeingOffered(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('admin.two_factor.required needs admin.two_factor.enabled');

        self::process([['two_factor' => ['required' => true]]]);
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
                // Vide = une seule image rendue, sans classe de visibilité : le comportement
                // d'avant l'ajout du nœud, à l'octet près.
                'logo_dark' => '',
                'favicon' => '',
                'home_route' => 'admin_dashboard',
                'logo_width' => null,
                'show_name' => true,
                // Les couleurs historiques du layout d'e-mail : un produit qui ne déclare rien ne voit rien changer.
                'email' => [
                    'background' => '#f1f5f9',
                    'panel' => '#ffffff',
                    'border' => '#e2e8f0',
                    'text' => '#334155',
                    'heading' => '#1e293b',
                    'muted' => '#94a3b8',
                    'accent' => '#1e293b',
                    'accent_text' => '#ffffff',
                    'logo' => 'light',
                ],
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
                'two_factor' => 'admin_account_two_factor',
                'privacy' => '',
            ],
            // ⚠️ Le clavier est ACTIF par défaut et son catalogue d'extension est vide : le socle
            // apporte ses trois actions, et une application n'en déclare que si elle en a.
            'keyboard' => [
                'enabled' => true,
                'actions' => [],
            ],
            'mercure' => [
                'hub_url' => '',
                'token_route' => '',
            ],
            // Empty by default: the guide link only appears once a product says where its guide is.
            'user_guide' => [
                'route' => '',
                'url' => '',
            ],
            'two_factor' => [
                'enabled' => false,
                'required' => false,
                'issuer' => null,
                'exempt_routes' => [],
                'open_routes' => [],
            ],
        ], $this->process([]));
    }

    /**
     * A guide is either a route of the application OR an address: both at once leaves the shell
     * guessing which one wins, so the configuration says no.
     */
    public function testAUserGuideIsARouteOrAnUrlNotBoth(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process([['user_guide' => ['route' => 'public_guide_index', 'url' => '/docs']]]);
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

    /** Une couleur d'e-mail s'écrit dans un attribut `style` : rien d'autre qu'un #rrggbb n'y entre. */
    public function testAnEmailColourMustBeAHexLiteral(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('admin.branding.email.accent expects a #rrggbb colour');

        $this->process([['branding' => ['email' => ['accent' => 'red; background: url(x)']]]]);
    }

    /** Une palette illisible est refusée au démarrage, pas découverte dans une boîte mail. */
    public function testAnIllegibleEmailPaletteIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('accent_text on accent is');

        $this->process([['branding' => ['email' => ['accent' => '#f59e0b', 'accent_text' => '#ffffff']]]]);
    }

    /** Une palette de nuit lisible passe, logo sombre compris. */
    public function testANightEmailPaletteIsAccepted(): void
    {
        $config = $this->process([['branding' => ['email' => [
            'background' => '#0e0a1a', 'panel' => '#151026', 'border' => '#2a1f45', 'text' => '#dcd0e6',
            'heading' => '#f8f1fa', 'muted' => '#ada0c0', 'accent' => '#ff4f9a', 'accent_text' => '#1a0b14', 'logo' => 'dark',
        ]]]]);

        $branding = $config['branding'];
        self::assertIsArray($branding);
        $email = $branding['email'];
        self::assertIsArray($email);
        self::assertSame('dark', $email['logo']);
        self::assertSame('#ff4f9a', $email['accent']);
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
