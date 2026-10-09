<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

use DateTimeImmutable;

/**
 * What the inbox (`@Admin/inbox/_layout.html.twig`) needs to know about a message to list it —
 * and nothing else. The bundle knows no entity: a contact request, a support ticket, a public
 * enquiry each plug their own class in.
 *
 * ```php
 * #[ORM\Entity]
 * class ContactMessage implements InboxMessageInterface
 * {
 *     public function getInboxId(): int { return $this->id; }
 *     public function getSenderName(): string { return $this->name; }
 *     public function getSenderEmail(): string { return $this->email; }
 *     public function getInboxExcerpt(): string { return mb_substr($this->message, 0, 140); }
 *     public function getReceivedAt(): DateTimeImmutable { return $this->receivedAt; }
 *     public function isRead(): bool { return null !== $this->readAt; }
 *     public function getInboxStatusLabel(): ?string { return 'contact_message.status.answered'; }
 * }
 * ```
 *
 * ⚠️ **The excerpt is plain text, short, and escaped by the template.** It is what the list shows
 * under the sender; the full message belongs to the reader, which the application renders itself.
 */
interface InboxMessageInterface
{
    /** The identifier the message route expects (`inbox.message_route`, parameter `id`). */
    public function getInboxId(): int|string;

    public function getSenderName(): string;

    public function getSenderEmail(): string;

    /** A short, plain-text start of the message, for the list. */
    public function getInboxExcerpt(): string;

    public function getReceivedAt(): \DateTimeImmutable;

    /** Unread messages carry a dot, and a word for screen readers. */
    public function isRead(): bool;

    /**
     * A translation key for the status the list shows beside the date (answered, archived…), in
     * the `inbox.translation_domain` domain — or null for none.
     */
    public function getInboxStatusLabel(): ?string;
}
