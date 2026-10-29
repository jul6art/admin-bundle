<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\EventListener\BackupCodeUsedListener;
use Jul6Art\AdminBundle\EventListener\TwoFactorEnforcementListener;
use Jul6Art\AdminBundle\EventListener\TwoFactorThrottleListener;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\TwoFactor\TotpEnrolment;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversNothing;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
    private const array WITH_BACKUP_CODES = ['two_factor' => ['enabled' => true, 'backup_codes' => true]];

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
        $this->expectExceptionMessageIsOrContains('SchebTwoFactorBundle');

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

    /**
     * Une application à plusieurs espaces importe l'écran une fois par espace : il s'affiche dans
     * le layout de l'espace et y revient — un client ne passe jamais par le préfixe de la plateforme.
     */
    public function testTheScreenImportedInASecondAreaKeepsItsLayoutAndItsPrefix(): void
    {
        $account = $this->signIn(self::OFFERED);

        $page = $this->send($this->request('GET', '/app/account/two-factor'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('data-area-layout', (string) $page->getContent());

        $secret = $account->getTotpAuthenticationConfiguration()?->getSecret();
        if (null === $secret || '' === $secret) {
            self::fail('L\'écran pose un secret en attente.');
        }
        self::assertSame(1, preg_match('/name="two_factor_enable\[_token\]"[^>]*value="([^"]+)"/', (string) $page->getContent(), $token));
        $done = $this->send($this->request('POST', '/app/account/two-factor', ['two_factor_enable' => ['code' => TOTP::createFromSecret($secret)->now(), '_token' => html_entity_decode($token[1])]]));

        self::assertSame('/app/account/two-factor', $done->headers->get('Location'));
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
        self::assertSame(200, $this->send($this->request('GET', '/app/account/two-factor'))->getStatusCode(), 'Comme chacun de ses imports.');
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

    public function testBackupCodesNeedTheSecondFactorItself(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('admin.two_factor.backup_codes needs admin.two_factor.enabled');

        $this->boot(bundleConfig: ['two_factor' => ['backup_codes' => true]], withOrm: true, withTwoFactor: true);
    }

    public function testOfferingBackupCodesWiresSchebAndTheAuditEvent(): void
    {
        self::assertFalse($this->boot(bundleConfig: self::OFFERED, withOrm: true, withTwoFactor: true)->has(BackupCodeUsedListener::class));

        $container = $this->boot(bundleConfig: self::WITH_BACKUP_CODES, withOrm: true, withTwoFactor: true);

        self::assertTrue($container->has(BackupCodeUsedListener::class));
        self::assertTrue($container->has('scheb_two_factor.backup_code_manager'), 'scheb vérifie les codes de secours à la connexion.');
    }

    /**
     * L'activation tire dix codes, montrés sur la page qui suit et sur elle seule ; ensuite l'écran les
     * compte. Un nouveau jeu demande un code en cours, et remplace l'ancien.
     */
    public function testActivationShowsTheBackupCodesOnceThenANewSetTakesACurrentCode(): void
    {
        $account = $this->signIn(self::WITH_BACKUP_CODES);
        $secret = $this->secretOf($account, $this->get());

        self::assertSame(302, $this->post('two_factor_enable', TOTP::createFromSecret($secret)->now(), $this->get())->getStatusCode());

        $shown = (string) $this->get()->getContent();
        self::assertSame(10, preg_match_all('/<li>([A-Z2-9]{5}-[A-Z2-9]{5})<\/li>/', $shown, $codes));
        self::assertTrue($account->isBackupCode($codes[1][0]));

        $again = $this->get();
        self::assertStringNotContainsString('data-new-backup-codes', (string) $again->getContent(), 'Une seule fois : recharger ne les remontre pas.');
        self::assertStringContainsString('data-backup-codes-left="10"', (string) $again->getContent());

        self::assertSame(422, $this->post('two_factor_backup_codes', '000000', $again)->getStatusCode());
        self::assertSame(302, $this->post('two_factor_backup_codes', TOTP::createFromSecret($secret)->now(), $this->get())->getStatusCode());
        self::assertStringContainsString('data-new-backup-codes', (string) $this->get()->getContent());
        self::assertFalse($account->isBackupCode($codes[1][0]), 'L\'ancien jeu ne fonctionne plus.');
    }

    /**
     * ⚠️ Le téléphone perdu : sans code en cours, un code de secours retire le second facteur.
     */
    public function testABackupCodeTurnsItOffOnTheScreen(): void
    {
        $account = $this->signIn(self::WITH_BACKUP_CODES);
        $account->startTotpEnrolment('JBSWY3DPEHPK3PXP')->confirmTotp(new \DateTimeImmutable())->replaceBackupCodes(['K7QM2-XW9RT']);

        $off = $this->post('two_factor_disable', 'k7qm2-xw9rt', $this->get());

        self::assertSame(302, $off->getStatusCode());
        self::assertFalse($account->isTotpAuthenticationEnabled());
        self::assertSame(0, $account->countBackupCodes());
    }

    public function testTheSignInCodePageOffersABackupCodeOnlyWhenThereAreSome(): void
    {
        self::assertStringContainsString('data-totp-backup', $this->renderCodePage(self::WITH_BACKUP_CODES));
        self::assertStringContainsString('id="_auth_backup_code"', $this->renderCodePage(self::WITH_BACKUP_CODES));
        self::assertStringNotContainsString('data-totp-backup', $this->renderCodePage(self::OFFERED));
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function renderCodePage(array $bundleConfig): string
    {
        $container = $this->boot(bundleConfig: $bundleConfig, withOrm: true, withTwoFactor: true);
        $this->pushRequest($container);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('@Admin/security/two_factor.html.twig', [
            'authenticationError' => null,
            'authenticationErrorData' => [],
            'checkPathUrl' => '/2fa_check',
            'checkPathRoute' => null,
            'authCodeParameterName' => '_auth_code',
            'isCsrfProtectionEnabled' => true,
            'csrfParameterName' => '_csrf_token',
            'csrfTokenId' => 'two_factor',
            'logoutPath' => '/logout',
        ]);
    }

    /**
     * @return non-empty-string
     */
    private function secretOf(Account $account, Response $page): string
    {
        self::assertStringContainsString('data-totp-qr', (string) $page->getContent());
        $secret = $account->getTotpAuthenticationConfiguration()?->getSecret();
        if (null === $secret || '' === $secret) {
            self::fail('L\'écran pose un secret en attente.');
        }

        return $secret;
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
