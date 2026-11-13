<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Account\Event;

use Symfony\Component\HttpFoundation\Request;

/**
 * A sign-up written: the account has its identifier.
 *
 * What needs the identifier, or must never leave for an account that does not exist, listens here: the link to
 * confirm the address (signed on the identifier), the acknowledgement, the alert to whoever validates accounts.
 */
final readonly class UserRegisteredEvent
{
    public function __construct(
        public object $account,
        public Request $request,
    ) {
    }
}
