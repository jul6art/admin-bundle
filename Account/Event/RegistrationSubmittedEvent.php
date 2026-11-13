<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Account\Event;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A valid sign-up, its password hashed, about to be written.
 *
 * A listener completes the account in the same flush: the language it signed up in, the terms it accepted, the
 * proof of its consents. What it persists is written with the account — never one without the other.
 */
final readonly class RegistrationSubmittedEvent
{
    /**
     * @param FormInterface<covariant mixed> $form
     */
    public function __construct(
        public object $account,
        public Request $request,
        public FormInterface $form,
    ) {
    }
}
