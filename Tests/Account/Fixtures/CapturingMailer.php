<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account\Fixtures;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Keeps what it is given — and the reset link's token as it was at sending time: the controller clears the token
 * object right after, as a real transport would have rendered the e-mail first.
 */
final class CapturingMailer implements MailerInterface
{
    /** @var list<Email> */
    public array $sent = [];

    /** @var list<string> */
    public array $tokens = [];

    #[\Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (!$message instanceof Email) {
            return;
        }

        $this->sent[] = $message;
        $token = $message instanceof TemplatedEmail ? ($message->getContext()['resetToken'] ?? null) : null;
        if ($token instanceof ResetPasswordToken) {
            $this->tokens[] = $token->getToken();
        }
    }
}
