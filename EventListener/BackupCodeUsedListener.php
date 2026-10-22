<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\EventListener;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Jul6Art\AdminBundle\TwoFactor\TwoFactorEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorCodeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A backup code accepted at sign-in becomes the bundle's own `TwoFactorEvent::BACKUP_CODE_USED`: an
 * application listens to one name, wherever the code was spent (scheb's sign-in, or the account
 * screen through `TotpEnrolment::verify()`).
 */
final readonly class BackupCodeUsedListener
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function onValid(TwoFactorCodeEvent $event): void
    {
        $account = $event->getUser();
        if ($account instanceof TwoFactorAwareInterface) {
            $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::BACKUP_CODE_USED);
        }
    }
}
