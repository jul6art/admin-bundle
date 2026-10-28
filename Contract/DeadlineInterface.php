<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

/**
 * Something to renew before a date — an API secret, a service account's token, an annual review:
 * what `admin.deadlines` lists on its screen and reminds of by e-mail at D-7, D-1 and on the day.
 *
 * The bundle knows no entity: the application declares its own, using
 * {@see \Jul6Art\AdminBundle\Entity\Traits\DeadlineTrait} for every method below but `getId()`, and
 * names it in `admin.deadlines.entity`. It must be constructible without arguments — the screen and
 * the providers' synchronisation create it with `new`.
 */
interface DeadlineInterface
{
    public function getId(): int|string|null;

    public function getLabel(): string;

    public function setLabel(string $label): static;

    /** Null only on an entity the form has not filled yet. */
    public function getDueOn(): ?\DateTimeImmutable;

    /** A new date re-arms the three reminders; the same date keeps what was already sent. */
    public function setDueOn(\DateTimeImmutable $dueOn): static;

    /** Days between two renewals, when there is a rhythm — « Renewed » then sets today + this. */
    public function getPeriodDays(): ?int;

    public function setPeriodDays(?int $periodDays): static;

    /** Where it is renewed: the console, the portal. Linked from the screen and from the e-mails. */
    public function getRenewUrl(): ?string;

    public function setRenewUrl(?string $renewUrl): static;

    public function getNote(): ?string;

    public function setNote(?string $note): static;

    /** The key of the provider that computes it; null for a deadline typed in the screen. */
    public function getSource(): ?string;

    public function setSource(?string $source): static;

    /** The last reminder sent for the current date: 7, 1 or 0 days before it — null for none yet. */
    public function getNotifiedStage(): ?int;

    public function markNotified(int $stage): static;

    /** Renewed today: the date becomes today plus the period. Without a period, nothing changes. */
    public function renew(\DateTimeImmutable $today): static;
}
