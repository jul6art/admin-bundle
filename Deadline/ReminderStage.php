<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Deadline;

/**
 * The three reminders of a deadline, by the days left when they are due: a week before, the day
 * before, and the day itself — or later, as long as nothing was renewed.
 */
enum ReminderStage: int
{
    case Week = 7;
    case Eve = 1;
    case Day = 0;

    /** The reminder a deadline this many days away has reached; null further than a week. */
    public static function reachedAt(int $daysLeft): ?self
    {
        return match (true) {
            $daysLeft <= 0 => self::Day,
            $daysLeft <= 1 => self::Eve,
            $daysLeft <= 7 => self::Week,
            default => null,
        };
    }
}
