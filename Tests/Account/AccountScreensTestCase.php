<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\CapturingMailer;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * A browser on {@see AccountTestKernel}, a fresh database, and what the tests read back: the accounts, the e-mails.
 */
abstract class AccountScreensTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $account = $options['account'] ?? [];

        return new AccountTestKernel(\is_array($account) ? $account : []);
    }

    /**
     * @param array<string, mixed> $account the "admin.account" configuration the scenario needs
     */
    protected function start(array $account = []): KernelBrowser
    {
        $this->client = static::createClient(['account' => $account]);
        // One kernel for the whole scenario: the listeners a test adds and the session survive each request.
        $this->client->disableReboot();

        $schema = new SchemaTool($this->entityManager());
        $metadata = $this->entityManager()->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        // The limiter's counters live in a filesystem pool: one scenario's submissions must not count in the next.
        $limits = static::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $limits);
        $limits->clear();

        return $this->client;
    }

    protected function member(string $email, string $password = 'the old password', string $locale = 'en'): Member
    {
        $member = new Member()->setEmail($email)->setPassword($password)->setFirstName('Ada')->setLastName('Lovelace')->setLocale($locale);
        $this->entityManager()->persist($member);
        $this->entityManager()->flush();

        return $member;
    }

    protected function find(string $email): ?Member
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Member::class)->findOneBy(['email' => $email]);
    }

    protected function entityManager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    protected function mailer(): CapturingMailer
    {
        $mailer = static::getContainer()->get(MailerInterface::class);
        self::assertInstanceOf(CapturingMailer::class, $mailer);

        return $mailer;
    }

    protected function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }

    /**
     * @return list<string>
     */
    protected function flashes(string $type): array
    {
        $session = $this->client->getRequest()->getSession();
        $bag = $session->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $bag);

        return array_values(array_filter($bag->peek($type), \is_string(...)));
    }
}
