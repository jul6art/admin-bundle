<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\Form\AppearanceType;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

/**
 * Les gabarits sont rendus pour de vrai.
 *
 * Vérifier qu'une extension Twig est enregistrée ne dit pas qu'un gabarit l'atteint, et une
 * coquille n'a pas de sortie attendue tant que personne ne la rend : c'est le seul niveau où une
 * balise mal fermée, un bloc mal nommé ou un `path()` sur une route vide se voient.
 */
#[CoversNothing]
final class ShellRenderingTest extends AbstractFunctionalTestCase
{
    private const array BRANDING = ['branding' => ['name' => 'Acme Admin', 'logo' => 'img/logo.png', 'home_route' => 'admin_dashboard']];

    public function testTheLayoutRendersTheBrandingAndTheMenu(): void
    {
        $html = $this->render('@Admin/layout.html.twig', self::BRANDING);

        self::assertStringContainsString('Acme Admin', $html);
        self::assertStringContainsString('/img/logo.png', $html);
        self::assertStringContainsString('nav.widget_list', $html, 'Une entrée non gardée doit apparaître.');
        self::assertStringNotContainsString('nav.widget_secret', $html, 'Une entrée gardée sans la permission ne doit pas apparaître.');
        self::assertStringNotContainsString('nav.secret', $html, 'Une section gardée sans la permission ne doit pas apparaître.');
    }

    /**
     * L'entrée de la liste reste allumée sur une page de détail. C'est un préfixe de nom de route
     * et non une égalité : sinon la barre latérale se vide dès qu'on ouvre une fiche.
     */
    public function testTheActiveItemIsHighlightedOnADetailPage(): void
    {
        $html = $this->render('@Admin/layout.html.twig', self::BRANDING, route: 'admin_widget_show');

        self::assertStringContainsString('border-accent-500', $html);
    }

    /**
     * Une route non configurée MASQUE son lien. `path('')` serait une 500 sur chaque page du
     * back-office, pour un lien optionnel.
     */
    public function testAnUnconfiguredRouteHidesItsLinkInsteadOfBreakingThePage(): void
    {
        $html = $this->render('@Admin/layout.html.twig', self::BRANDING, user: new Account()->setEmail('a@b.test')->setFullName('A B'));

        self::assertStringNotContainsString('nav.profile', $html, 'Aucune route de profil configurée : pas de lien.');
        self::assertStringContainsString('nav.appearance', $html, 'La route d\'apparence a un défaut : le lien est là.');
    }

    public function testTheAppearanceOfTheSignedInAccountReachesTheHtmlTag(): void
    {
        $account = new Account()
            ->setEmail('ada@example.test')
            ->setFullName('Ada Lovelace')
            ->setAccent(AccentColor::Teal)
            ->setDensity(DisplayDensity::Compact)
            ->setColorMode(ColorMode::Dark);

        $html = $this->render('@Admin/layout.html.twig', self::BRANDING, user: $account);

        self::assertStringContainsString('data-theme="dark"', $html);
        self::assertStringContainsString('data-accent="teal"', $html);
        self::assertStringContainsString('data-density="compact"', $html);
        // La classe `dark` de Tailwind, posée côté serveur : c'est elle qui évite le flash de
        // thème clair au premier rendu.
        self::assertStringContainsString('class="fixed-html dark"', $html);
        self::assertStringContainsString('Ada Lovelace', $html);
        self::assertStringContainsString('AL', $html, 'Les initiales servent de pastille en l\'absence de photo.');
    }

    public function testAnAnonymousPageCarriesNoAppearanceAttributeAtAll(): void
    {
        $html = $this->render('@Admin/security/login.html.twig', self::BRANDING);

        self::assertStringNotContainsString('data-accent', $html, 'Un data-accent="" ne matche pas [data-accent=\'teal\'] mais matche [data-accent] — mieux vaut pas d\'attribut du tout.');
    }

    public function testTheLoginPageCarriesItsCsrfTokenAndTheBranding(): void
    {
        $html = $this->render('@Admin/security/login.html.twig', self::BRANDING + ['routes' => ['register' => 'admin_security_register']]);

        self::assertStringContainsString('name="_username"', $html);
        self::assertStringContainsString('name="_password"', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString('Acme Admin', $html);
        self::assertStringContainsString('/register', $html, 'L\'inscription est configurée : le lien apparaît.');
    }

    /**
     * L'inscription se ferme en n'en déclarant pas la route. Un back-office interne n'a pas de
     * page publique de création de compte, et la fermer ne doit pas demander de surcharger un
     * gabarit.
     */
    public function testClosingPublicSignUpRemovesTheLinkFromTheLoginCard(): void
    {
        $html = $this->render('@Admin/security/login.html.twig', self::BRANDING);

        self::assertStringNotContainsString('security.login.create_account', $html);
    }

    /**
     * ⚠️ Le test qui manquait, et le défaut qu'il a rattrapé : `choice.vars.value` d'un enfant de
     * `ChoiceType` étendu est la CHAÎNE de la vue, pas le cas d'enum. Appeler `->swatch()` dessus
     * lève au rendu — et seulement sur cette page-là, que rien d'autre ne rendait.
     *
     * Un gabarit qu'aucun test ne rend est un gabarit dont on ne sait rien.
     */
    public function testTheAppearanceFormRendersItsFourWidgets(): void
    {
        $container = $this->boot(bundleConfig: self::BRANDING);
        $this->pushRequest($container, 'admin_account_appearance_edit');

        $formFactory = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $formFactory->create(AppearanceType::class, new Account()->setEmail('a@b.test')->setFullName('A B'));
        $html = $twig->render('@Admin/partials/_appearance_form.html.twig', ['form' => $form->createView()]);

        // Les trois modes en cartes, les sept accents en pastilles, la densité et l'échelle en
        // contrôles segmentés — aucun `<select>`, sans quoi personne ne découvre le réglage.
        self::assertStringContainsString('name="appearance[colorMode]"', $html);
        self::assertStringContainsString('value="system"', $html);
        self::assertStringContainsString('background-color: #6366f1', $html, 'La pastille indigo doit porter sa couleur de référence.');
        self::assertStringContainsString('background-color: #0d9488', $html, 'Et la pastille teal la sienne.');
        self::assertStringContainsString('name="appearance[density]"', $html);
        self::assertStringContainsString('name="appearance[fontScale]"', $html);
        self::assertStringContainsString('name="appearance[highContrast]"', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    public function testTheMercureMetaTagsAppearOnlyWhenConfigured(): void
    {
        self::assertStringNotContainsString('mercure-hub', $this->render('@Admin/layout.html.twig', self::BRANDING));

        $html = $this->render('@Admin/layout.html.twig', self::BRANDING + [
            'mercure' => ['hub_url' => 'https://hub.example.test/.well-known/mercure'],
        ]);
        self::assertStringContainsString('name="mercure-hub"', $html);
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function render(string $template, array $bundleConfig, ?Account $user = null, string $route = 'admin_widget_index'): string
    {
        $container = $this->boot(bundleConfig: $bundleConfig);

        $this->pushRequest($container, $route);

        if ($user instanceof Account) {
            $tokenStorage = $container->get('security.token_storage');
            self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render($template);
    }

    private function pushRequest(ContainerInterface $container, string $route): void
    {
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);

        // `app.request`, `app.flashes` et `csrf_token()` en ont tous besoin ; sans requête le rendu
        // échoue sur « no session available », ce qui ressemble à un défaut du gabarit.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', $route);
        $requestStack->push($request);
    }
}
