<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\EventListener\TwoFactorPassedListener;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\Authorization\Voter\TwoFactorInProgressVoter;
use Scheb\TwoFactorBundle\Security\Http\Utils\RequestDataReader;
use Scheb\TwoFactorBundle\Security\TwoFactor\TwoFactorFirewallConfig;
use Scheb\TwoFactorBundle\Security\TwoFactor\TwoFactorFirewallContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * An account signed in already, refused on the code page or its check — the code sent twice, the page opened again with
 * "Back" —, goes on where scheb sends it once the code passes; anything else keeps its answer.
 */
#[CoversClass(TwoFactorPassedListener::class)]
final class TwoFactorPassedListenerTest extends TestCase
{
    private const string CONTEXT = 'security.firewall.map.context.main';

    public function testTheCodeSentAgainGoesWhereTheCodeWouldHaveSentIt(): void
    {
        // scheb's authenticator, on its check path, refuses a token that is not at its code.
        $event = $this->event(self::signedIn(), new AccessDeniedException('User is not in a two-factor authentication process.'), Request::create('/2fa_check', 'POST'));

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/dashboard', $response->getTargetUrl());
        self::assertTrue($event->isPropagationStopped(), 'The firewall\'s 403 never answers.');
    }

    public function testTheCodePageOpenedAgainToo(): void
    {
        // `^/2fa` (IS_AUTHENTICATED_2FA_IN_PROGRESS), as the firewall wraps the refusal.
        $denied = new AccessDeniedException();
        $denied->setAttributes([TwoFactorInProgressVoter::IS_AUTHENTICATED_2FA_IN_PROGRESS]);

        $event = $this->event(self::signedIn(), new AccessDeniedHttpException('Access Denied.', $denied), Request::create('/2fa'));

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testEverythingElseKeepsItsAnswer(): void
    {
        $signingIn = new TwoFactorToken(self::signedIn(), null, 'main', ['totp']);
        $check = static fn (): Request => Request::create('/2fa_check', 'POST');
        foreach ([
            'a sign-in still at its code' => $this->event($signingIn, new AccessDeniedException(), $check()),
            'an anonymous visitor' => $this->event(null, new AccessDeniedException(), $check()),
            'a refusal elsewhere' => $this->event(self::signedIn(), new AccessDeniedException(), Request::create('/admin/users')),
            'another error' => $this->event(self::signedIn(), new \RuntimeException('Boom'), $check()),
        ] as $case => $event) {
            self::assertNull($event->getResponse(), $case);
        }
    }

    public function testAFirewallWithoutTheSecondFactorKeepsItsAnswer(): void
    {
        $event = $this->event(self::signedIn(), new AccessDeniedException(), Request::create('/2fa_check', 'POST'), 'api');

        self::assertNull($event->getResponse());
    }

    private function event(?TokenInterface $token, \Throwable $error, Request $request, string $firewall = 'main'): ExceptionEvent
    {
        $httpUtils = new HttpUtils();
        $contexts = new Container();
        $contexts->set(self::CONTEXT, new FirewallContext([], null, null, new FirewallConfig($firewall, 'security.user_checker')));
        $container = new Container();
        $container->set('security.firewall.map', new FirewallMap($contexts, [self::CONTEXT => null]));
        $storage = new TokenStorage();
        $storage->setToken($token);

        $listener = new TwoFactorPassedListener(
            $storage,
            new Security($container),
            new TwoFactorFirewallContext(['main' => new TwoFactorFirewallConfig(['auth_form_path' => '/2fa', 'check_path' => '/2fa_check', 'default_target_path' => '/dashboard'], 'main', $httpUtils, new RequestDataReader())]),
            $httpUtils,
        );
        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $error);
        $listener($event);

        return $event;
    }

    private static function signedIn(): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new Account()->setEmail('ada@example.test'), 'main', ['ROLE_USER']);
    }
}
