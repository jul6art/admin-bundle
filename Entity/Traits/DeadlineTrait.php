<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The columns of a deadline ({@see \Jul6Art\AdminBundle\Contract\DeadlineInterface}): everything
 * but the identifier, which the application's entity declares in its own way.
 *
 * A trait and not a mapped entity of the bundle, for the reason the appearance trait gives: this
 * ecosystem maps no vendor namespace, and the application keeps its table name and its migrations.
 *
 * `due_on` is nullable in the database only because a new entity reaches the form empty; the screen
 * refuses to save one without a date.
 */
trait DeadlineTrait
{
    #[ORM\Column(length: 120)]
    private string $label = '';

    #[ORM\Column(name: 'due_on', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueOn = null;

    #[ORM\Column(name: 'period_days', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $periodDays = null;

    #[ORM\Column(name: 'renew_url', length: 500, nullable: true)]
    private ?string $renewUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 80, unique: true, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'notified_stage', type: Types::SMALLINT, nullable: true)]
    private ?int $notifiedStage = null;

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDueOn(): ?\DateTimeImmutable
    {
        return $this->dueOn;
    }

    public function setDueOn(\DateTimeImmutable $dueOn): static
    {
        // Le même jour garde l'objet en place : Doctrine compare les dates par identité, et un nouvel
        // objet du même jour réécrirait la ligne à chaque synchronisation. Un autre jour réarme les rappels.
        if ($this->dueOn?->format('Y-m-d') === $dueOn->format('Y-m-d')) {
            return $this;
        }
        $this->notifiedStage = null;
        $this->dueOn = $dueOn;

        return $this;
    }

    public function getPeriodDays(): ?int
    {
        return $this->periodDays;
    }

    public function setPeriodDays(?int $periodDays): static
    {
        $this->periodDays = $periodDays;

        return $this;
    }

    public function getRenewUrl(): ?string
    {
        return $this->renewUrl;
    }

    public function setRenewUrl(?string $renewUrl): static
    {
        $this->renewUrl = $renewUrl;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getNotifiedStage(): ?int
    {
        return $this->notifiedStage;
    }

    public function markNotified(int $stage): static
    {
        $this->notifiedStage = $stage;

        return $this;
    }

    public function renew(\DateTimeImmutable $today): static
    {
        if (null !== $this->periodDays && $this->periodDays > 0) {
            $this->setDueOn($today->modify(\sprintf('+%d days', $this->periodDays)));
        }

        return $this;
    }
}
