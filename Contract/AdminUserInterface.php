<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

/**
 * What the shell shows about the signed-in account: a name, two initials, maybe a picture.
 *
 * Optional. An account that does not implement it is displayed by its `getUserIdentifier()` — an
 * e-mail address, usually — which is correct and slightly ugly. Implementing three methods makes
 * it right.
 *
 * Deliberately **not** merged with {@see AppearanceAwareInterface}: an application may well want
 * the appearance preferences without giving the shell a display name, or the reverse. Two narrow
 * contracts are implemented à la carte; one wide one is implemented with stubs.
 */
interface AdminUserInterface
{
    /** Full name, or whatever a human recognises the account by. */
    public function getDisplayName(): string;

    /** One or two letters for the avatar placeholder. */
    public function getInitials(): string;

    /** Asset path of the avatar, or null for the initials placeholder. */
    public function getAvatarPath(): ?string;
}
