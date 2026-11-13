<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Account\Event;

use Symfony\Component\HttpFoundation\Request;

/**
 * The sign-up page is about to build its form, around an account that does not exist yet.
 *
 * A listener prepares the account the form will fill — the personal space a customer is born with, before
 * validation reads it — and hands the form its options: a captcha's question, the address of the terms.
 */
final class RegistrationFormEvent
{
    /** @var array<string, mixed> */
    private array $formOptions = [];

    public function __construct(
        public readonly object $account,
        public readonly Request $request,
    ) {
    }

    public function setFormOption(string $name, mixed $value): void
    {
        $this->formOptions[$name] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return $this->formOptions;
    }
}
