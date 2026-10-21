<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\EventListener\TwoFactorEnforcementListener;
use Jul6Art\AdminBundle\EventListener\TwoFactorThrottleListener;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\TwoFactor\ExemptRoutesCondition;
use Jul6Art\AdminBundle\TwoFactor\TwoFactorEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[CoversClass(TwoFactorEnforcementListener::class)]
#[CoversClass(TwoFactorThrottleListener::class)]
#[CoversClass(ExemptRoutesCondition::class)]
final class TwoFactorListenersTest extends TestCase
{
    public function testARequiredSecondFactorSendsAnAccountWithoutItToItsEnrolment(): void
    {
        $event = $this->request('/admin/widgets', 'admin_widget_index', new Account()->setEmail('ada@example.test'));
        $this->enforcement($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/account/two-factor', $response->getTargetUrl());
    }

    public function testABackgroundOrApiCallGetsA403RatherThanARedirect(): void
    {
        $event = $this->request('/api/widgets', 'api_widgets', new Account()->setEmail('ada@example.test'));
        $this->enforcement($event);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
    }

    public function testTheEnrolmentScreenAndTheOpenRoutesStayReachable(): void
    {
        foreach (['admin_account_two_factor', 'admin_security_logout'] as $route) {
            $event = $this->request('/x', $route, new Account()->setEmail('ada@example.test'));
            $this->enforcement($event);
            self::assertNull($event->getResponse(), $route);
        }
    }

    public function testAnAccountWithTheSecondFactorAndOneSigningInAreLeftAlone(): void
    {
        $enabled = new Account()->setEmail('ada@example.test')->startTotpEnrolment('S')->confirmTotp(new \DateTimeImmutable());
        $event = $this->request('/admin/widgets', 'admin_widget_index', $enabled);
        $this->enforcement($event);
        self::assertNull($event->getResponse());

        // Pendant la saisie du code, le jeton est celui de scheb : la connexion elle-même.
        $signingIn = new Account()->setEmail('bob@example.test');
        $event = $this->request('/admin/widgets', 'admin_widget_index', $signingIn, twoFactorInProgress: true);
        $this->enforcement($event);
        self::assertNull($event->getResponse());
    }

    /**
     * Cinq échecs par compte : au-delà, même le bon code est refusé avant d'être lu ; chaque échec
     * est diffusé, un succès remet le compteur à zéro.
     */
    public function testTheCodeDoesNotFallToBruteForce(): void
    {
        $failures = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TwoFactorEvent::FAILED, static function () use (&$failures): void {
            ++$failures;
        });
        $listener = new TwoFactorThrottleListener(
            new RateLimiterFactory(['id' => 'admin_two_factor', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '15 minutes'], new InMemoryStorage()),
            $dispatcher,
        );
        $event = new TwoFactorAuthenticationEvent(new Request(), new UsernamePasswordToken(new Account()->setEmail('ada@example.test'), 'main'));

        for ($i = 0; $i < 5; ++$i) {
            $listener->onAttempt($event);
            $listener->onFailure($event);
        }
        self::assertSame(5, $failures);

        try {
            $listener->onAttempt($event);
            self::fail('Le sixième essai est refusé avant d\'être lu.');
        } catch (CustomUserMessageAuthenticationException $e) {
            self::assertSame('two_factor.throttled', $e->getMessageKey());
        }

        $listener->onComplete($event);
        $listener->onAttempt($event);
        $this->addToAssertionCount(1);
    }

    public function testOnlyTheExemptRoutesSkipTheCode(): void
    {
        $condition = new ExemptRoutesCondition(['dev_login_link_check']);

        self::assertFalse($condition->shouldPerformTwoFactorAuthentication($this->context('dev_login_link_check')));
        self::assertTrue($condition->shouldPerformTwoFactorAuthentication($this->context('admin_security_login')));
    }

    private function enforcement(RequestEvent $event): void
    {
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/account/two-factor');
        $tokenStorage = new TokenStorage();
        $token = $event->getRequest()->attributes->get('_token_for_test');
        self::assertInstanceOf(TokenInterface::class, $token);
        $tokenStorage->setToken($token);

        new TwoFactorEnforcementListener($tokenStorage, $urls, 'admin_account_two_factor', ['admin_account_two_factor', 'admin_security_logout'])($event);
    }

    private function request(string $path, string $route, Account $account, bool $twoFactorInProgress = false): RequestEvent
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);
        $token = new UsernamePasswordToken($account, 'main');
        $request->attributes->set('_token_for_test', $twoFactorInProgress ? new TwoFactorToken($token, null, 'main', ['totp']) : $token);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function context(string $route): AuthenticationContextInterface
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $context = self::createStub(AuthenticationContextInterface::class);
        $context->method('getRequest')->willReturn($request);

        return $context;
    }
}
