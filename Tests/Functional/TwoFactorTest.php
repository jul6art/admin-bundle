<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\EventListener\TwoFactorEnforcementListener;
use Jul6Art\AdminBundle\EventListener\TwoFactorThrottleListener;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\TwoFactor\TotpEnrolment;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversNothing;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

/**
 * La double authentification optionnelle : le projet la PROPOSE (`enabled`), et l'IMPOSE ou non
 * (`required`). Prouvée en démarrant le conteneur — la moitié de ce qui casse dans un bundle est du
 * câblage — et en passant l'écran par le noyau, comme un compte le ferait.
 */
#[CoversNothing]
final class TwoFactorTest extends AbstractFunctionalTestCase
{
    private const array OFFERED = ['two_factor' => ['enabled' => true, 'issuer' => 'Acme']];
    private const array REQUIRED = ['two_factor' => ['enabled' => true, 'required' => true, 'open_routes' => ['site_home']]];

    private ?Session $session = null;

    public function testNothingIsRegisteredUntilTheApplicationOffersIt(): void
    {
        $container = $this->boot(withOrm: true, withTwoFactor: true);

        self::assertFalse($container->has(TotpEnrolment::class));
        self::assertFalse($container->has(TwoFactorThrottleListener::class));
        self::assertSame('', $container->getParameter('admin.route.two_factor'), 'Pas d\'entrée de menu vers un écran qui n\'existe pas.');
    }

    public function testOfferingItWiresTheScreenAndTheLimitButNoObligation(): void
    {
        $container = $this->boot(bundleConfig: self::OFFERED, withOrm: true, withTwoFactor: true);

        self::assertTrue($container->has(TotpEnrolment::class));
        self::assertTrue($container->has(TwoFactorThrottleListener::class));
        self::assertFalse($container->has(TwoFactorEnforcementListener::class));
        self::assertSame('admin_account_two_factor', $container->getParameter('admin.route.two_factor'));

        // scheb est configuré POUR l'application : l'émetteur que voit l'appli d'authentification.
        $totp = $container->get('scheb_two_factor.security.totp_authenticator');
        self::assertInstanceOf(TotpAuthenticatorInterface::class, $totp);
        self::assertStringContainsString('issuer=Acme', $totp->getQRContent(new Account()->setEmail('ada@example.test')->startTotpEnrolment('JBSWY3DPEHPK3PXP')));
    }

    public function testRequiringItRegistersTheObligationWithItsOpenRoutes(): void
    {
        $container = $this->boot(bundleConfig: self::REQUIRED, withOrm: true, withTwoFactor: true);

        self::assertTrue($container->has(TwoFactorEnforcementListener::class));
        $open = $container->getParameter('admin.two_factor.open_routes');
        self::assertIsArray($open);
        foreach (['admin_account_two_factor', 'admin_security_logout', '2fa_login', '2fa_login_check', 'site_home'] as $route) {
            self::assertContains($route, $open);
        }
    }

    public function testOfferingItWithoutSchebRegisteredSaysWhatToDo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SchebTwoFactorBundle');

        $this->boot(bundleConfig: self::OFFERED, withOrm: true);
    }

    public function testTheAccountMenuOffersTheScreenOnlyWhenItExists(): void
    {
        self::assertStringContainsString('/admin/account/two-factor', $this->renderLayout(self::OFFERED));
        self::assertStringNotContainsString('/admin/account/two-factor', $this->renderLayout([]));
    }

    /**
     * Le parcours d'un compte : le QR code et la clé, un premier code faux qui ne change rien, le
     * bon qui active, puis la désactivation par un code en cours.
     */
    public function testAnAccountEnrolsWithAFirstRightCodeAndTurnsItOffWithACurrentOne(): void
    {
        $account = $this->signIn(self::OFFERED);

        $page = $this->get();
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('data-totp-qr', (string) $page->getContent());
        self::assertTrue($account->isTotpEnrolmentPending());
        $secret = $account->getTotpAuthenticationConfiguration()?->getSecret();
        if (null === $secret || '' === $secret) {
            self::fail('L\'écran pose un secret en attente.');
        }

        $wrong = $this->post('two_factor_enable', '000000', $page);
        self::assertSame(422, $wrong->getStatusCode());
        self::assertFalse($account->isTotpAuthenticationEnabled());

        $right = $this->post('two_factor_enable', TOTP::createFromSecret($secret)->now(), $this->get());
        self::assertSame(302, $right->getStatusCode());
        self::assertTrue($account->isTotpAuthenticationEnabled());

        $enabled = $this->get();
        self::assertStringContainsString('data-totp-enabled', (string) $enabled->getContent());
        self::assertStringNotContainsString('data-totp-secret', (string) $enabled->getContent(), 'Le secret ne se montre qu\'à l\'enrôlement.');

        $off = $this->post('two_factor_disable', TOTP::createFromSecret($secret)->now(), $enabled);
        self::assertSame(302, $off->getStatusCode());
        self::assertFalse($account->isTotpAuthenticationEnabled());
    }

    public function testARequiredSecondFactorCannotBeTurnedOff(): void
    {
        $account = $this->signIn(self::REQUIRED);
        $account->startTotpEnrolment('JBSWY3DPEHPK3PXP')->confirmTotp(new \DateTimeImmutable());

        $page = $this->get();
        self::assertSame(200, $page->getStatusCode());
        self::assertStringNotContainsString('two_factor_disable', (string) $page->getContent());
    }

    public function testARequiredSecondFactorSendsAnAccountWithoutItToItsEnrolment(): void
    {
        $this->signIn(self::REQUIRED);

        $response = $this->send($this->request('GET', '/admin/widgets'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/account/two-factor', $response->headers->get('Location'));
        self::assertSame(200, $this->get()->getStatusCode(), 'L\'écran d\'enrôlement, lui, reste ouvert.');
    }

    public function testTheSignInCodePageIsTheAuthenticationCard(): void
    {
        $container = $this->boot(bundleConfig: self::OFFERED, withOrm: true, withTwoFactor: true);
        $this->pushRequest($container);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('@Admin/security/two_factor.html.twig', [
            'authenticationError' => 'code_invalid',
            'authenticationErrorData' => [],
            'checkPathUrl' => '/2fa_check',
            'checkPathRoute' => null,
            'authCodeParameterName' => '_auth_code',
            'isCsrfProtectionEnabled' => true,
            'csrfParameterName' => '_csrf_token',
            'csrfTokenId' => 'two_factor',
            'logoutPath' => '/logout',
        ]);

        self::assertStringContainsString('auth-shell', $html);
        self::assertStringContainsString('name="_auth_code"', $html);
        self::assertStringContainsString('autocomplete="one-time-code"', $html);
        self::assertStringContainsString('data-totp-error', $html);
        self::assertStringContainsString('action="/2fa_check"', $html);
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function signIn(array $bundleConfig): Account
    {
        $container = $this->boot(bundleConfig: $bundleConfig, withOrm: true, withTwoFactor: true);
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $account = new Account()->setEmail('ada@example.test')->setFullName('Ada Lovelace');
        $entityManager->persist($account);
        $entityManager->flush();

        // Le noyau remet ses services à zéro au DÉBUT de chaque requête — le stockage de jetons
        // compris. Sans pare-feu dans ce noyau de test, un écouteur prioritaire « reconnecte » le
        // compte avant tout le reste, comme le ferait la session.
        $tokenStorage = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(KernelEvents::REQUEST, static function () use ($tokenStorage, $account): void {
            $tokenStorage->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));
        }, 2048);
        $this->session = new Session(new MockArraySessionStorage());

        return $account;
    }

    private function get(): Response
    {
        return $this->send($this->request('GET', '/admin/account/two-factor'));
    }

    private function send(Request $request): Response
    {
        return $this->handle($request);
    }

    private function post(string $form, string $code, Response $page): Response
    {
        self::assertSame(1, preg_match('/name="'.$form.'\[_token\]"[^>]*value="([^"]+)"/', (string) $page->getContent(), $token), 'Le formulaire porte son jeton.');

        return $this->send($this->request('POST', '/admin/account/two-factor', [$form => ['code' => $code, '_token' => html_entity_decode($token[1])]]));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function request(string $method, string $uri, array $parameters = []): Request
    {
        $request = Request::create('http://localhost'.$uri, $method, $parameters);
        self::assertInstanceOf(Session::class, $this->session);
        $request->setSession($this->session);

        return $request;
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function renderLayout(array $bundleConfig): string
    {
        $container = $this->boot(bundleConfig: $bundleConfig, withOrm: true, withTwoFactor: true);
        $this->pushRequest($container);
        $tokenStorage = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $account = new Account()->setEmail('ada@example.test')->setFullName('Ada Lovelace');
        $tokenStorage->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('@Admin/layout.html.twig');
    }

    private function pushRequest(ContainerInterface $container): void
    {
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $request = Request::create('http://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', 'admin_widget_index');
        $requestStack->push($request);
    }
}
