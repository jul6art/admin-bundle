<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\DependencyInjection\Configuration;
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

    /**
     * ⚠️ TOUT gabarit de page du bundle passe par `admin_base_template()` — le layout ET la carte
     * d'authentification. C'est l'indirection qui permet au projet d'injecter sa chaîne d'assets ;
     * un `extends '@Admin/base.html.twig'` en dur rend la page SANS la moindre feuille de style,
     * sans erreur nulle part. Le bug a existé : corrigé pour `layout.html.twig` chez superp, il
     * avait été oublié dans `security/_card.html.twig` et ne s'est vu qu'au premier projet qui a
     * rendu ces pages (wovex, 2026-08-23).
     */
    public function testEveryPageTemplateHonoursTheProjectBaseTemplate(): void
    {
        $config = self::BRANDING + ['base_template' => 'project_base.html.twig'];

        $layout = $this->render('@Admin/layout.html.twig', $config, user: new Account()->setEmail('a@b.test')->setFullName('A B'));
        self::assertStringContainsString('data-project-assets="loaded"', $layout, 'Le layout doit rendre à travers le base template du projet.');

        $login = $this->render('@Admin/security/login.html.twig', $config);
        self::assertStringContainsString('data-project-assets="loaded"', $login, 'Les pages d\'authentification aussi — elles portaient le bug.');
    }

    /**
     * ⚠️ Toute clé de `admin.routes` doit atteindre Twig.
     *
     * La table est reconstituée à la main dans `Resources/config/twig.yaml`, une ligne par clé :
     * ajouter une entrée à la configuration sans l'ajouter LÀ produit un paramètre correctement
     * rempli que `admin_route()` ne voit jamais — le lien ne rend pas, et rien ne le signale.
     * C'est arrivé avec `performance` (2026-08-23).
     */
    public function testEveryConfiguredRouteKeyReachesTwig(): void
    {
        $defaults = new Configuration()->getConfigTreeBuilder()->buildTree()->finalize([]);
        self::assertIsArray($defaults);
        $routes = $defaults['routes'];
        self::assertIsArray($routes);

        $configured = array_keys($routes);

        $wiring = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/config/twig.yaml');

        foreach ($configured as $key) {
            self::assertStringContainsString(
                \sprintf("%s: '%%admin.route.%s%%'", $key, $key),
                $wiring,
                \sprintf('La clé « %s » est configurable mais n\'est pas passée à l\'extension Twig.', $key),
            );
        }
    }

    /**
     * La case « se souvenir de moi » est un INTERRUPTEUR, comme toute case de la coquille.
     *
     * Elle était le dernier `<input type="checkbox">` nu des gabarits du bundle : une case
     * système au milieu d'un formulaire stylé, seule de son espèce. Les jetons `.toggle-*`
     * appartiennent à ce bundle (`assets/styles/components.css`), donc rien n'est emprunté au
     * projet — le vocabulaire est déjà là.
     */
    public function testTheRememberMeBoxIsASwitchLikeEveryOtherBox(): void
    {
        $html = $this->render('@Admin/security/login.html.twig', self::BRANDING);

        self::assertStringContainsString('toggle-switch-input', $html);
        self::assertStringContainsString('toggle-switch-track', $html);
        self::assertStringContainsString('name="_remember_me"', $html, 'Le nom du champ est le contrat de `remember_me` : il ne bouge pas.');
        self::assertStringNotContainsString('rounded border-slate-300 text-accent-600', $html, 'La case système est ce qui a été remplacé.');
    }

    /**
     * `logo_width` dimensionne le logo des pages d'authentification ; sans lui, la hauteur fixe
     * historique s'applique. `show_name` retire la ligne du nom sous le logo — le cas d'un
     * wordmark, où le nom serait écrit deux fois.
     */
    public function testTheAuthCardHonoursLogoWidthAndShowName(): void
    {
        $config = ['branding' => self::BRANDING['branding'] + ['logo_width' => 220, 'show_name' => false]];
        $html = $this->render('@Admin/security/login.html.twig', $config);

        self::assertStringContainsString('width: 220px', $html);
        self::assertStringNotContainsString('text-lg font-semibold', $html, 'show_name: false doit retirer la ligne du nom.');

        $html = $this->render('@Admin/security/login.html.twig', self::BRANDING);
        self::assertStringContainsString('h-12', $html, 'Sans logo_width, la hauteur fixe historique s\'applique.');
        self::assertStringContainsString('text-lg font-semibold', $html, 'Par défaut, le nom reste affiché.');
    }

    /**
     * `show_name: false` vaut aussi pour la barre latérale : le logo y prend toute la largeur au
     * lieu de partager la ligne avec un nom qu'il écrit déjà.
     */
    public function testTheSidebarBrandHonoursShowName(): void
    {
        $config = ['branding' => self::BRANDING['branding'] + ['show_name' => false]];
        $html = $this->render('@Admin/layout.html.twig', $config, user: new Account()->setEmail('a@b.test')->setFullName('A B'));

        self::assertStringContainsString('/img/logo.png', $html);
        self::assertStringNotContainsString('text-sm font-semibold text-slate-700', $html, 'Le nom de la sidebar doit disparaître.');
    }

    /**
     * Le layout d'e-mail est brandé et AUTONOME : un client mail ne charge ni Tailwind ni le CSS
     * du back-office, donc tout est inline, et le logo part en URL ABSOLUE — un chemin relatif ne
     * pointe sur rien depuis une boîte mail.
     */
    public function testTheEmailLayoutIsBrandedAndSelfContained(): void
    {
        $config = ['branding' => self::BRANDING['branding'] + ['logo_width' => 220]];
        $html = $this->render('email/test_email.html.twig', $config);

        self::assertStringContainsString('http://localhost/img/logo.png', $html, 'Le logo doit être une URL absolue.');
        self::assertStringContainsString(self::BRANDING['branding']['name'], $html);
        self::assertStringContainsString('data-email-body-marker', $html, 'Le bloc du template enfant doit rendre.');
        self::assertStringContainsString('https://example.test/cta', $html, 'Le bouton du partial doit porter son URL.');
        self::assertStringNotContainsString('class="', $html, 'Aucune classe CSS : les clients mail ne chargent pas de feuille de style.');
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
     * ⚠️ Un gabarit du bundle traverse le layout que `admin.layout_template` DÉSIGNE.
     *
     * Coder `@Admin/layout.html.twig` en dur fait qu'une page du bundle contourne le layout du
     * projet — et le symptôme est sournois : la page rend parfaitement, il lui manque seulement
     * ce que le projet ajoute. Constaté sur l'écran d'apparence (2026-08-24) : ni le sélecteur de
     * langue de la barre du haut, ni la marque de la barre latérale, ni `window.jwtToken` —
     * pendant que toutes les autres pages d'administration les avaient.
     *
     * Le test rend CHAQUE gabarit de page du bundle à travers un layout de projet et exige d'y
     * retrouver ses marqueurs : une page ajoutée demain sans l'indirection le fera échouer.
     */
    public function testEveryBundlePageGoesThroughTheProjectLayout(): void
    {
        $container = $this->boot(bundleConfig: array_merge(self::BRANDING, [
            'layout_template' => 'project_layout.html.twig',
        ]));
        $this->pushRequest($container, 'admin_account_appearance_edit');

        $formFactory = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $account = new Account()->setEmail('a@b.test')->setFullName('A B');
        $form = $formFactory->create(AppearanceType::class, $account);

        $html = $twig->render('@Admin/account/appearance.html.twig', ['form' => $form->createView()]);

        self::assertStringContainsString('data-project-topbar', $html, 'La barre du haut du projet est contournée.');
        self::assertStringContainsString('data-project-brand', $html, 'La marque du projet est contournée.');
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
        // `Request::create()` et non `new Request()` : le layout d'e-mail passe par
        // `absolute_url()`, qui lit le host de la requête courante — un `new Request()` nu n'en a
        // pas et produit « http://:/ ».
        $request = Request::create('http://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', $route);
        $requestStack->push($request);
    }
}
