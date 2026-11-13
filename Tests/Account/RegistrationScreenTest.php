<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Jul6Art\AdminBundle\Account\Event\RegistrationFormEvent;
use Jul6Art\AdminBundle\Account\Event\RegistrationSubmittedEvent;
use Jul6Art\AdminBundle\Account\Event\UserRegisteredEvent;
use Jul6Art\AdminBundle\Controller\Security\RegistrationController;
use Jul6Art\AdminBundle\Form\Security\RegistrationFormType;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The public sign-up (ADR-0001, T20 lot 3): closed, inactive or active, its three events, its limiter, its empty
 * fields, the account signed in at once.
 */
#[CoversClass(RegistrationController::class)]
#[CoversClass(RegistrationFormType::class)]
final class RegistrationScreenTest extends AccountScreensTestCase
{
    private const string PASSWORD = 'a passphrase long enough';

    public function testAClosedSignUpDoesNotExist(): void
    {
        $this->start(['registration' => ['open' => false]]);

        $this->client->request('GET', '/register');

        self::assertResponseStatusCodeSame(404, 'Not 403, which would confirm the route exists.');
    }

    public function testANewAccountArrivesInactiveAndWaits(): void
    {
        $this->start();

        $this->signUp($this->client->request('GET', '/register'), ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => 'Ada@Example.test']);

        self::assertResponseRedirects('/login');
        self::assertSame(['security.register.flash.pending'], $this->flashes('success'));
        $member = $this->find('ada@example.test');
        self::assertInstanceOf(Member::class, $member);
        self::assertFalse($member->isActive());
        self::assertSame('Ada', $member->getFirstName());
        self::assertSame(self::PASSWORD, $member->getPassword(), 'Hashed by the application\'s hasher (plaintext in this test).');
        self::assertNull($member->getPlainPassword());
    }

    public function testSelfServiceActivatesAndSaysSo(): void
    {
        $this->start(['registration' => ['active' => true, 'flash' => 'security.register.flash.created']]);

        $this->signUp($this->client->request('GET', '/register'), ['email' => 'ada@example.test']);

        self::assertSame(['security.register.flash.created'], $this->flashes('success'));
        self::assertTrue($this->find('ada@example.test')?->isActive());
    }

    public function testTheThreeEventsPrepareCompleteAndFollowTheAccount(): void
    {
        $this->start();
        $seen = [];
        $this->dispatcher()->addListener(RegistrationFormEvent::class, static function (RegistrationFormEvent $event) use (&$seen): void {
            self::assertInstanceOf(Member::class, $event->account);
            $seen[] = 'form';
            // Before validation reads it: what the form needs on the account, and an option of the form.
            $event->account->setLocale('nl');
            $event->setFormOption('attr', ['data-prepared' => 'yes']);
        });
        $this->dispatcher()->addListener(RegistrationSubmittedEvent::class, static function (RegistrationSubmittedEvent $event) use (&$seen): void {
            self::assertInstanceOf(Member::class, $event->account);
            self::assertNull($event->account->getId(), 'Before the write.');
            self::assertTrue($event->form->isValid());
            $seen[] = 'submitted';
            $event->account->completeBy('the listener');
        });
        $this->dispatcher()->addListener(UserRegisteredEvent::class, static function (UserRegisteredEvent $event) use (&$seen): void {
            self::assertInstanceOf(Member::class, $event->account);
            self::assertNotNull($event->account->getId(), 'After the write: the identifier exists.');
            $seen[] = 'registered';
        });

        $crawler = $this->client->request('GET', '/register');
        self::assertSame('yes', $crawler->filter('form')->attr('data-prepared'));
        $this->signUp($crawler, ['email' => 'ada@example.test']);

        self::assertSame(['form', 'form', 'submitted', 'registered'], $seen, 'The form event on each page, the two others once.');
        $member = $this->find('ada@example.test');
        self::assertInstanceOf(Member::class, $member);
        self::assertSame('the listener', $member->getCompletedBy(), 'Written in the same flush as the account.');
        self::assertSame('nl', $member->getLocale());
    }

    public function testAnEmptiedFieldIsAFormErrorNotAServerError(): void
    {
        $this->start();
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->filter('form')->form(['registration_form[firstName]' => '', 'registration_form[email]' => '']));

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->find(''));
    }

    public function testEverySubmissionCountsAgainstTheLimiter(): void
    {
        $this->start(['registration' => ['limiter' => 'limiter.sign_up']]);
        $crawler = $this->client->request('GET', '/register');

        // Three submissions per hour in this test: invalid ones count too.
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->client->submit($crawler->filter('form')->form(['registration_form[email]' => 'not an address']));
            self::assertResponseStatusCodeSame(422);
        }

        $this->signUp($crawler, ['email' => 'ada@example.test']);
        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('form', 'security.register.too_many_attempts');
        self::assertNull($this->find('ada@example.test'), 'Refused before anything is written or sent.');
    }

    public function testTheAuthenticatorSignsTheNewAccountInAtOnce(): void
    {
        $this->start(['registration' => ['active' => true, 'sign_in' => 'form_login']]);

        $this->signUp($this->client->request('GET', '/register'), ['email' => 'ada@example.test']);

        self::assertResponseRedirects('/admin', null, 'The form login\'s success handler: its default target.');
        $this->client->request('GET', '/admin/account/profile');
        self::assertResponseIsSuccessful('Signed in: the profile opens.');
    }

    /**
     * @param array<string, string> $fields
     */
    private function signUp(Crawler $crawler, array $fields): void
    {
        $values = [
            'registration_form[plainPassword][first]' => self::PASSWORD,
            'registration_form[plainPassword][second]' => self::PASSWORD,
        ];
        foreach ($fields as $field => $value) {
            $values['registration_form['.$field.']'] = $value;
        }

        $this->client->submit($crawler->filter('form')->form($values));
    }
}
