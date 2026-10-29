<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\Command\DeadlineRemindCommand;
use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Deadline\DeadlineNotifier;
use Jul6Art\AdminBundle\Deadline\ProvidedDeadline;
use Jul6Art\AdminBundle\Deadline\ReminderStage;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Deadline;
use Jul6Art\AdminBundle\Tests\Fixtures\RecordingMailer;
use Jul6Art\AdminBundle\Tests\Fixtures\TokenDeadlineProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The deadlines to renew (1.36): three reminders, each once per date; a missed one is sent late,
 * alone; a new date re-arms them; the application's own deadlines follow it; the screen lists,
 * creates, renews and deletes — behind the attribute the application chose.
 */
#[CoversNothing]
final class DeadlineTest extends AbstractFunctionalTestCase
{
    private const array CONFIG = ['deadlines' => [
        'entity' => Deadline::class,
        'recipients' => 'ops@example.test, ,jul@example.test',
        'from' => 'noreply@example.test',
        'access' => 'ROLE_USER',
        'locale' => 'fr',
        'timezone' => 'Europe/Brussels',
    ]];

    private ?ContainerInterface $container = null;

    private ?Session $session = null;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // 23:30 UTC le 27, c'est déjà le 28 à Bruxelles (UTC+1 depuis le 25 octobre) : « aujourd'hui »
        // se lit dans le fuseau configuré.
        Clock::set(new MockClock('2026-10-27 23:30:00', 'UTC'));
    }

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        $this->container = null;
        parent::tearDown();
    }

    public function testNothingIsRegisteredWithoutAnEntity(): void
    {
        $container = $this->boot(withOrm: true);

        self::assertFalse($container->has(DeadlineNotifier::class));
    }

    public function testAnEntityThatIsNotADeadlineIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('admin.deadlines.entity must name a class implementing');

        $this->boot(bundleConfig: ['deadlines' => ['entity' => Account::class]], withOrm: true);
    }

    public function testTheScreensAccessIsReadableByTheApplicationsMenuTest(): void
    {
        $this->start();

        self::assertSame('ROLE_USER', $this->container?->getParameter('admin.deadlines.access'));
    }

    public function testTodayIsReadInTheConfiguredTimezone(): void
    {
        $this->start();

        self::assertSame('2026-10-28', $this->notifier()->today()->format('Y-m-d'));
    }

    public function testTheThreeRemindersGoOnceEach(): void
    {
        $this->start();
        $this->deadline('Secret Entra', '2026-11-04', renewUrl: 'https://entra.microsoft.com/');

        self::assertSame([ReminderStage::Week], $this->stages($this->notifier()->remind()));
        self::assertSame([], $this->notifier()->remind(), 'Twice the same day: nothing more.');

        $mail = $this->mailer()->sent[0];
        self::assertSame(['ops@example.test', 'jul@example.test'], array_map(static fn (Address $address): string => $address->getAddress(), $mail->getTo()));
        self::assertSame('noreply@example.test', $mail->getFrom()[0]->getAddress());
        self::assertSame('deadline.email.subject', $mail->getSubject(), 'The application\'s catalogue writes it.');
        self::assertStringContainsString('Secret Entra', (string) $mail->getHtmlBody());
        self::assertStringContainsString('https://entra.microsoft.com/', (string) $mail->getHtmlBody());
        self::assertStringContainsString('http://localhost/admin/deadlines', (string) $mail->getTextBody(), 'The screen, linked.');

        $this->travel('2026-11-03 07:00:00');
        self::assertSame([ReminderStage::Eve], $this->stages($this->notifier()->remind()));
        $this->travel('2026-11-04 07:00:00');
        self::assertSame([ReminderStage::Day], $this->stages($this->notifier()->remind()));
        $this->travel('2026-11-06 07:00:00');
        self::assertSame([], $this->notifier()->remind(), 'Past, and the day itself already sent.');
        self::assertCount(3, $this->mailer()->sent);
    }

    public function testAMissedReminderGoesLateAloneAndANewDateReArmsThem(): void
    {
        $this->start();
        $deadline = $this->deadline('Compte de simulation Saxo', '2026-10-29', period: 20);

        self::assertSame([ReminderStage::Eve], $this->stages($this->notifier()->remind()), 'One day left, nothing sent yet: the most urgent alone, never the D-7 too.');

        $deadline->renew($this->notifier()->today());
        self::assertSame('2026-11-17', $deadline->getDueOn()?->format('Y-m-d'));
        self::assertNull($deadline->getNotifiedStage(), 'Renewed: re-armed.');
        $this->travel('2026-11-10 07:00:00');
        self::assertSame([ReminderStage::Week], $this->stages($this->notifier()->remind()));
    }

    public function testWithoutRecipientNothingIsSentNorMarked(): void
    {
        $this->start(['deadlines' => ['recipients' => ''] + self::CONFIG['deadlines']]);
        $deadline = $this->deadline('Secret Entra', '2026-10-30');

        self::assertSame([], $this->notifier()->remind());
        self::assertNull($deadline->getNotifiedStage(), 'The day the address is set, the reminder goes.');
    }

    public function testTheApplicationsDeadlinesFollowItAndGoWithIt(): void
    {
        $this->start();
        $this->provider()->deadlines = [new ProvidedDeadline('psn.npsso', 'NPSSO PSN', new \DateTimeImmutable('2026-11-01'), 'https://ca.account.sony.com/')];

        self::assertSame([ReminderStage::Week], $this->stages($this->notifier()->remind()));
        $row = $this->entityManager()->getRepository(Deadline::class)->findOneBy(['source' => 'psn.npsso']) ?? self::fail('Not written.');
        self::assertSame(['NPSSO PSN', '2026-11-01'], [$row->getLabel(), $row->getDueOn()?->format('Y-m-d')]);
        $written = $row->getDueOn();
        $this->notifier()->synchronise();
        self::assertSame($written, $row->getDueOn(), 'The same day keeps its object: Doctrine would rewrite the row on every visit.');
        self::assertSame(ReminderStage::Week->value, $row->getNotifiedStage());

        // Un nouveau NPSSO : la date suit, les rappels repartent.
        $this->provider()->deadlines = [new ProvidedDeadline('psn.npsso', 'NPSSO PSN', new \DateTimeImmutable('2026-12-27'))];
        $this->notifier()->synchronise();
        $row = $this->entityManager()->getRepository(Deadline::class)->findOneBy(['source' => 'psn.npsso']) ?? self::fail('Gone.');
        self::assertSame(['2026-12-27', null], [$row->getDueOn()?->format('Y-m-d'), $row->getNotifiedStage()]);

        $this->provider()->deadlines = [];
        $this->notifier()->synchronise();
        self::assertSame([], $this->entityManager()->getRepository(Deadline::class)->findAll(), 'No token, no deadline.');
    }

    public function testTheCommandSaysWhatItSent(): void
    {
        $this->start();
        $this->deadline('Validation Google', '2026-10-28');

        $command = $this->service(DeadlineRemindCommand::class, DeadlineRemindCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Reminder D-0: Validation Google', $tester->getDisplay());
    }

    public function testTheScreenListsTheNearestFirstAndTheirReminders(): void
    {
        $this->signIn();
        $this->deadline('Plus tard', '2027-03-01');
        $this->deadline('Expirée', '2026-10-20');
        $this->deadline('Bientôt', '2026-11-02', period: 20);
        $this->provider()->deadlines = [new ProvidedDeadline('psn.npsso', 'NPSSO PSN', new \DateTimeImmutable('2026-11-20'))];

        $page = $this->send('GET', '/admin/deadlines');

        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $html = (string) $page->getContent();
        self::assertSame(1, preg_match_all('/data-status="past"/', $html));
        self::assertMatchesRegularExpression('/Expirée.*Bientôt.*NPSSO PSN.*Plus tard/s', $html, 'The nearest first.');
        self::assertStringContainsString('aria-valuenow="75"', $html, '15 of 20 days gone.');
        self::assertStringContainsString('deadline.provided', $html, 'The NPSSO says the application computes it.');
        self::assertSame(1, preg_match_all('#/admin/deadlines/\d+/renew#', $html), 'Only a deadline with a rhythm is renewed in one click.');
        self::assertStringContainsString('data-deadline-reminders', $html);
    }

    public function testADeadlineIsTypedCorrectedRenewedAndDeleted(): void
    {
        $this->signIn();

        $form = $this->send('GET', '/admin/deadlines/new');
        self::assertSame(Response::HTTP_OK, $form->getStatusCode());
        $refused = $this->send('POST', '/admin/deadlines/new', ['deadline' => ['label' => ' ', 'dueOn' => '', 'periodDays' => '0', 'renewUrl' => 'ftp://x', '_token' => $this->token($form, 'deadline\[_token\]')]]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        foreach (['deadline.error.label', 'deadline.error.due_on', 'deadline.error.period_days', 'deadline.error.renew_url'] as $error) {
            self::assertStringContainsString($error, (string) $refused->getContent());
        }

        $saved = $this->send('POST', '/admin/deadlines/new', ['deadline' => ['label' => ' Compte Saxo ', 'dueOn' => '2026-11-03', 'periodDays' => '20', 'renewUrl' => 'https://www.developer.saxo/', 'note' => '', '_token' => $this->token($form, 'deadline\[_token\]')]]);
        self::assertSame(Response::HTTP_FOUND, $saved->getStatusCode());
        $deadline = $this->entityManager()->getRepository(Deadline::class)->findOneBy([]) ?? self::fail('Not saved.');
        self::assertSame(['Compte Saxo', 20], [$deadline->getLabel(), $deadline->getPeriodDays()]);

        $list = $this->send('GET', '/admin/deadlines');
        $id = $deadline->getId();
        $this->send('POST', \sprintf('/admin/deadlines/%d/renew', $id), ['_token' => $this->token($list, '_token')]);
        $deadline = $this->entityManager()->find(Deadline::class, $id) ?? self::fail('Gone.');
        self::assertSame('2026-11-17', $deadline->getDueOn()?->format('Y-m-d'), 'Today plus twenty days.');

        $edit = $this->send('GET', \sprintf('/admin/deadlines/%d/edit', $deadline->getId()));
        self::assertStringContainsString('deadline.edit.danger_title', (string) $edit->getContent());
        $this->send('POST', \sprintf('/admin/deadlines/%d/delete', $deadline->getId()), ['_token' => $this->token($edit, '_token', '/delete"')]);
        self::assertSame([], $this->entityManager()->getRepository(Deadline::class)->findAll());
    }

    public function testADeadlineTheApplicationComputesIsNotEditedHere(): void
    {
        $this->signIn();
        $this->provider()->deadlines = [new ProvidedDeadline('psn.npsso', 'NPSSO PSN', new \DateTimeImmutable('2026-11-20'))];
        $this->notifier()->synchronise();
        $row = $this->entityManager()->getRepository(Deadline::class)->findOneBy(['source' => 'psn.npsso']) ?? self::fail('Not written.');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->send('GET', \sprintf('/admin/deadlines/%d/edit', $row->getId()))->getStatusCode());
    }

    public function testTheScreenAsksTheConfiguredAttribute(): void
    {
        $this->signIn(['deadlines' => ['access' => 'ROLE_SUPER_ADMIN'] + self::CONFIG['deadlines']]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->send('GET', '/admin/deadlines')->getStatusCode());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function start(array $config = self::CONFIG): void
    {
        $this->container = $this->boot(bundleConfig: $config, withOrm: true);
        new SchemaTool($this->entityManager())->createSchema($this->entityManager()->getMetadataFactory()->getAllMetadata());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function signIn(array $config = self::CONFIG): void
    {
        $this->start($config);
        $account = new Account()->setEmail('ada@example.test')->setFullName('Ada Lovelace');
        $this->entityManager()->persist($account);
        $this->entityManager()->flush();

        // Comme l'écran de double authentification : sans pare-feu dans ce noyau, un écouteur
        // prioritaire « reconnecte » le compte au début de chaque requête.
        $tokenStorage = $this->service('security.token_storage', TokenStorageInterface::class);
        $this->service('event_dispatcher', EventDispatcherInterface::class)->addListener(KernelEvents::REQUEST, static function () use ($tokenStorage, $account): void {
            $tokenStorage->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));
        }, 2048);
        $this->session = new Session(new MockArraySessionStorage());
    }

    private function deadline(string $label, string $dueOn, ?int $period = null, ?string $renewUrl = null): Deadline
    {
        $deadline = new Deadline()->setLabel($label)->setDueOn(new \DateTimeImmutable($dueOn))->setPeriodDays($period)->setRenewUrl($renewUrl);
        $this->entityManager()->persist($deadline);
        $this->entityManager()->flush();

        return $deadline;
    }

    private function travel(string $now): void
    {
        Clock::set(new MockClock($now, 'UTC'));
    }

    /**
     * @param list<array{deadline: DeadlineInterface, stage: ReminderStage}> $sent
     *
     * @return list<ReminderStage>
     */
    private function stages(array $sent): array
    {
        return array_map(static fn (array $reminder): ReminderStage => $reminder['stage'], $sent);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function send(string $method, string $uri, array $parameters = []): Response
    {
        $request = Request::create('http://localhost'.$uri, $method, $parameters);
        self::assertInstanceOf(Session::class, $this->session);
        $request->setSession($this->session);

        return $this->handle($request);
    }

    /** The CSRF token of a field of the page — of the form whose action contains `$action`, when given. */
    private function token(Response $page, string $name, string $action = ''): string
    {
        $html = (string) $page->getContent();
        if ('' !== $action) {
            $html = substr($html, (int) strpos($html, $action));
        }
        self::assertSame(1, preg_match('/name="'.$name.'"[^>]*value="([^"]+)"/', $html, $token), 'The form carries its token.');

        return html_entity_decode($token[1]);
    }

    private function notifier(): DeadlineNotifier
    {
        return $this->service(DeadlineNotifier::class, DeadlineNotifier::class);
    }

    private function mailer(): RecordingMailer
    {
        return $this->service(MailerInterface::class, RecordingMailer::class);
    }

    private function provider(): TokenDeadlineProvider
    {
        return $this->service(TokenDeadlineProvider::class, TokenDeadlineProvider::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        self::assertInstanceOf(ContainerInterface::class, $this->container, 'start() first.');
        $service = $this->container->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
