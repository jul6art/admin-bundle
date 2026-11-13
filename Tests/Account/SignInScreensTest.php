<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Jul6Art\AdminBundle\Controller\Security\LoginController;
use Jul6Art\AdminBundle\Controller\Security\ResetPasswordController;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\CodeResetPasswordRequestType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Sign-in and the forgotten password, in the `admin` space and in a customer portal (ADR-0001, T20 lot 3).
 */
#[CoversClass(LoginController::class)]
#[CoversClass(ResetPasswordController::class)]
final class SignInScreensTest extends AccountScreensTestCase
{
    public function testTheCardShowsTheErrorAndTheLastAddress(): void
    {
        $this->start();
        $this->member('ada@example.test');

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form')->form(['_username' => 'ada@example.test', '_password' => 'wrong']));
        $crawler = $this->client->followRedirect();

        self::assertSame('ada@example.test', $crawler->filter('input[name="_username"]')->attr('value'));
        self::assertStringContainsString('Invalid credentials.', (string) $this->client->getResponse()->getContent());
    }

    public function testTheRightPasswordSignsInAndAnAdminSpaceAsksAgain(): void
    {
        $this->start();
        $this->member('ada@example.test');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(['_username' => 'ada@example.test', '_password' => 'the old password']));
        self::assertResponseRedirects('/admin');

        // The admin space has no `redirect_authenticated_to`: the card shows again, as before the bundle.
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }

    public function testASpaceWithAHomeSendsTheSignedInVisitorThere(): void
    {
        $this->start();
        $this->client->loginUser($this->member('ada@example.test'), 'portal');

        $this->client->request('GET', '/portal/login');

        self::assertResponseRedirects('/portal');
    }

    public function testThePortalRendersItsOwnCard(): void
    {
        $this->start();

        $crawler = $this->client->request('GET', '/portal/login');

        self::assertResponseIsSuccessful();
        self::assertSame('portal', $crawler->filter('[data-portal-login]')->attr('data-space'));
    }

    public function testTheSignOutIsNeverRun(): void
    {
        $this->start();
        $controller = self::getContainer()->get(LoginController::class);
        self::assertInstanceOf(LoginController::class, $controller);

        $this->expectException(\LogicException::class);
        $controller->logout();
    }

    public function testAKnownAndAnUnknownAddressTakeTheSameTime(): void
    {
        $this->start();
        $this->member('ada@example.test');

        $known = $this->requestReset('ADA@example.test ');
        $unknown = $this->requestReset('nobody@example.test');

        self::assertGreaterThanOrEqual(0.25, $known, 'The request never answers faster than its floor.');
        self::assertGreaterThanOrEqual(0.25, $unknown);
        self::assertLessThan(0.05, abs($known - $unknown), 'A known address and an unknown one take the same time, within 50 ms.');
        self::assertCount(1, $this->mailer()->sent, 'One e-mail, for the known address only.');
    }

    public function testTheEmailLeavesInTheAccountsLanguageWithTheSpaceOfTheRequest(): void
    {
        $this->start(['reset_password' => ['response_floor_ms' => 0]]);
        $this->member('ada@example.test', locale: 'nl');

        $this->requestReset('ada@example.test', '/portal/reset-password');
        self::assertResponseRedirects('/portal/reset-password/check-email');
        $this->client->followRedirect();
        self::assertSelectorExists('[data-portal-check-email]');
        self::assertNotSame('', $this->client->getCrawler()->filter('[data-portal-check-email]')->attr('data-lifetime'), 'The page says how long the link lives: it receives the token.');

        $email = $this->mailer()->sent[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('nl', $email->getLocale());
        self::assertSame('security.reset_password.email.subject', $email->getSubject(), 'Translated in the e-mail\'s language; the test has no catalog.');
        self::assertSame('noreply@example.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Demo', $email->getFrom()[0]->getName());
        self::assertSame('ada@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('security/reset_password_email.html.twig', $email->getHtmlTemplate());
        self::assertSame('portal', $email->getContext()['space'] ?? null);
        self::assertSame('nl', $email->getContext()['locale'] ?? null);
        self::assertInstanceOf(ResetPasswordToken::class, $email->getContext()['resetToken'] ?? null);
    }

    public function testTheLinkLeadsToANewPasswordThenToTheSignIn(): void
    {
        $this->start(['reset_password' => ['response_floor_ms' => 0]]);
        $this->member('ada@example.test');
        $this->requestReset('ada@example.test');
        $token = $this->mailer()->tokens[0] ?? null;
        self::assertIsString($token);

        // The token leaves the URL for the session at once.
        $this->client->request('GET', '/reset-password/reset/'.$token);
        self::assertResponseRedirects('/reset-password/reset');
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->filter('form')->form([
            'new_password_form[plainPassword][first]' => 'a brand new passphrase',
            'new_password_form[plainPassword][second]' => 'a different one, mistyped',
        ]));
        self::assertResponseStatusCodeSame(422);

        $this->client->submit($crawler->filter('form')->form([
            'new_password_form[plainPassword][first]' => 'a brand new passphrase',
            'new_password_form[plainPassword][second]' => 'a brand new passphrase',
        ]));
        self::assertResponseRedirects('/login');
        self::assertSame(['security.reset_password.flash.changed'], $this->flashes('success'));
        self::assertSame('a brand new passphrase', $this->find('ada@example.test')?->getPassword(), 'Written by auth-bundle\'s PasswordWriter (plaintext in this test).');
    }

    /**
     * The application puts its own type in the bundle's place, the bundle's as its parent: the screen names the form,
     * so the fields keep their names — `reset_password_request_form[…]` — and the theme its blocks.
     */
    public function testAnApplicationsTypeKeepsTheFieldsNames(): void
    {
        $this->start(['reset_password' => ['response_floor_ms' => 0, 'request_form_type' => CodeResetPasswordRequestType::class]]);
        $this->member('ada@example.test');

        $crawler = $this->client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="reset_password_request_form[code]"]'));

        $this->client->submit($crawler->filter('form')->form(['reset_password_request_form[email]' => 'ada@example.test', 'reset_password_request_form[code]' => '42']));

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertCount(1, $this->mailer()->sent);
    }

    public function testAnInvalidTokenSendsBackToTheRequest(): void
    {
        $this->start();

        $this->client->request('GET', '/reset-password/reset/not-a-token');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        self::assertSame(['security.reset_password.flash.invalid_token'], $this->flashes('error'));
    }

    public function testTheCheckPageNeverTellsWhetherAnEmailLeft(): void
    {
        $this->start();

        $this->client->request('GET', '/reset-password/check-email');
        self::assertResponseIsSuccessful();

        // No request in the session: a fake token, and the same page — its link's lifetime included.
        $crawler = $this->client->request('GET', '/portal/reset-password/check-email');
        self::assertResponseIsSuccessful();
        self::assertNotSame('', $crawler->filter('[data-portal-check-email]')->attr('data-lifetime'));
    }

    /**
     * @return float the seconds the request took
     */
    private function requestReset(string $email, string $path = '/reset-password'): float
    {
        $crawler = $this->client->request('GET', $path);
        $form = $crawler->filter('form')->form(['reset_password_request_form[email]' => $email]);

        $startedAt = hrtime(true);
        $this->client->submit($form);

        return (hrtime(true) - $startedAt) / 1e9;
    }
}
