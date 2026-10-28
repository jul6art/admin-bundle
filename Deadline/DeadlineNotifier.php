<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Deadline;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Ui\Branding;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The deadlines of `admin.deadlines`: what the screen lists, and the reminders — one e-mail at D-7,
 * one at D-1, one on the day (or after, while nothing was renewed), each sent once for a given
 * date.
 *
 * ## A reminder missed is not lost, and never doubled
 *
 * The run compares the stage a deadline has REACHED with the last one SENT: a worker stopped for
 * three days still sends, on its return, the most urgent stage reached — and that one alone, never
 * the D-7 and the D-1 the same morning. A new date (renewed, corrected) re-arms the three
 * ({@see \Jul6Art\AdminBundle\Entity\Traits\DeadlineTrait::setDueOn()}).
 *
 * ## Days, not hours
 *
 * « Today » is the date in `admin.deadlines.timezone`, and both dates are compared at midnight UTC:
 * a summer-time change never turns seven days into six.
 *
 * Nothing secret goes into a mail — the label, the date, the days left and two links — so a
 * forwarded reminder leaks nothing.
 */
final readonly class DeadlineNotifier
{
    public const string DOMAIN = 'deadline';

    /**
     * @param iterable<DeadlineProviderInterface> $providers
     * @param class-string<DeadlineInterface>     $entityClass
     * @param list<string>|string                 $recipients  a list, or a comma-separated string (an env var)
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private iterable $providers,
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private Branding $branding,
        private LoggerInterface $logger,
        private string $entityClass,
        private array|string $recipients,
        private string $from,
        private string $locale,
        private string $timezone,
    ) {
    }

    /** Today's date in the configured timezone, at midnight UTC. */
    public function today(): \DateTimeImmutable
    {
        $now = $this->clock->now();
        if ('' !== $this->timezone) {
            $now = $now->setTimezone(new \DateTimeZone($this->timezone));
        }

        return new \DateTimeImmutable($now->format('Y-m-d'), new \DateTimeZone('UTC'));
    }

    /** Days from today to the deadline: negative once it has passed, null without a date. */
    public function daysLeft(DeadlineInterface $deadline): ?int
    {
        $dueOn = $deadline->getDueOn();
        if (null === $dueOn) {
            return null;
        }

        $due = new \DateTimeImmutable($dueOn->format('Y-m-d'), new \DateTimeZone('UTC'));

        return (int) $this->today()->diff($due)->format('%r%a');
    }

    /**
     * Writes what the providers compute, then returns every deadline, the nearest first.
     *
     * @return list<DeadlineInterface>
     */
    public function synchronise(): array
    {
        $rows = [];
        foreach ($this->entityManager->getRepository($this->entityClass)->findAll() as $row) {
            if ($row instanceof DeadlineInterface) {
                $rows[] = $row;
            }
        }

        $provided = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->deadlines() as $deadline) {
                $provided[$deadline->source] = $deadline;
            }
        }

        $bySource = [];
        foreach ($rows as $index => $row) {
            $source = $row->getSource();
            if (null === $source) {
                continue;
            }
            if (!isset($provided[$source])) {
                // Plus de jeton, plus d'échéance : la source s'est tue.
                $this->entityManager->remove($row);
                unset($rows[$index]);
                continue;
            }
            $bySource[$source] = $row;
        }

        foreach ($provided as $source => $deadline) {
            $row = $bySource[$source] ?? null;
            if (null === $row) {
                $row = new ($this->entityClass)();
                $row->setSource($source);
                $this->entityManager->persist($row);
                $rows[] = $row;
            }
            $row->setLabel($deadline->label)->setDueOn($deadline->dueOn)->setRenewUrl($deadline->renewUrl)->setNote($deadline->note)->setPeriodDays(null);
        }

        $this->entityManager->flush();

        usort($rows, static fn (DeadlineInterface $a, DeadlineInterface $b): int => [$a->getDueOn()?->format('Y-m-d') ?? '9999', $a->getLabel()] <=> [$b->getDueOn()?->format('Y-m-d') ?? '9999', $b->getLabel()]);

        return array_values($rows);
    }

    /**
     * The morning run: synchronises, then sends the reminders due.
     *
     * @return list<array{deadline: DeadlineInterface, stage: ReminderStage}> what was sent
     */
    public function remind(): array
    {
        $recipients = $this->recipients();
        $sent = [];
        foreach ($this->synchronise() as $deadline) {
            $days = $this->daysLeft($deadline);
            $stage = null === $days ? null : ReminderStage::reachedAt($days);
            if (null === $days || null === $stage) {
                continue;
            }
            $notified = $deadline->getNotifiedStage();
            if (null !== $notified && $notified <= $stage->value) {
                continue;
            }
            if ([] === $recipients) {
                // Rien n'est marqué : le jour où l'adresse est posée, le rappel part.
                $this->logger->warning('admin.deadlines: a reminder is due but no recipient is configured.', ['deadline' => $deadline->getLabel()]);
                continue;
            }

            $this->mailer->send($this->email($deadline, $days, $recipients));
            $deadline->markNotified($stage->value);
            $sent[] = ['deadline' => $deadline, 'stage' => $stage];
        }

        $this->entityManager->flush();

        return $sent;
    }

    /**
     * @param non-empty-list<string> $recipients
     */
    private function email(DeadlineInterface $deadline, int $days, array $recipients): Email
    {
        $dueOn = $deadline->getDueOn() ?? $this->today();
        $date = $dueOn->format($this->translator->trans('deadline.date_format', [], self::DOMAIN, $this->locale));
        $parameters = ['%app%' => $this->branding->name, '%label%' => $deadline->getLabel(), '%date%' => $date, '%count%' => max(0, $days)];
        $past = $days < 0;

        $screen = null;
        try {
            $screen = $this->urls->generate('admin_deadline_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            // L'application n'a pas importé l'écran : l'e-mail se passe du lien.
        }

        $context = [
            'locale' => $this->locale,
            'label' => $deadline->getLabel(),
            'date' => $date,
            'days' => $days,
            'parameters' => $parameters,
            'past' => $past,
            'note' => $deadline->getNote(),
            'renew_url' => $deadline->getRenewUrl(),
            'screen_url' => $screen,
        ];

        $subject = $this->translator->trans($past ? 'deadline.email.subject_past' : 'deadline.email.subject', $parameters, self::DOMAIN, $this->locale);
        $email = new Email()
            ->to(...$recipients)
            ->subject($subject)
            ->html($this->twig->render('@Admin/email/deadline.html.twig', $context))
            ->text($this->twig->render('@Admin/email/deadline.txt.twig', $context));
        if ('' !== $this->from) {
            $email->from($this->from);
        }

        return $email;
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $recipients = \is_string($this->recipients) ? explode(',', $this->recipients) : $this->recipients;

        return array_values(array_filter(array_map(trim(...), $recipients), static fn (string $address): bool => '' !== $address));
    }
}
