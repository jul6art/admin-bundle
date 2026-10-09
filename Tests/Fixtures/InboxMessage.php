<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Jul6Art\AdminBundle\Contract\InboxMessageInterface;

/**
 * Un message de messagerie sans entité : le contrat suffit au gabarit.
 */
final readonly class InboxMessage implements InboxMessageInterface
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $excerpt,
        private \DateTimeImmutable $receivedAt,
        private bool $read = false,
        private ?string $status = null,
    ) {
    }

    #[\Override]
    public function getInboxId(): int
    {
        return $this->id;
    }

    #[\Override]
    public function getSenderName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getSenderEmail(): string
    {
        return $this->email;
    }

    #[\Override]
    public function getInboxExcerpt(): string
    {
        return $this->excerpt;
    }

    #[\Override]
    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    #[\Override]
    public function isRead(): bool
    {
        return $this->read;
    }

    #[\Override]
    public function getInboxStatusLabel(): ?string
    {
        return $this->status;
    }
}
