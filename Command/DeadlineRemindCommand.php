<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Command;

use Jul6Art\AdminBundle\Deadline\DeadlineNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The morning run of `admin.deadlines`: the providers' deadlines written, then the reminders due
 * sent ({@see DeadlineNotifier}). The application schedules it — once a day, in the morning, when
 * someone reads the mail:
 *
 * ```php
 * RecurringMessage::every('1 day', new RunCommandMessage('admin:deadlines:remind'), from: '08:00');
 * ```
 *
 * Run twice the same day, it sends nothing more: each stage goes once per date.
 */
#[AsCommand(name: 'admin:deadlines:remind', description: 'Sends the reminders of the deadlines to renew (D-7, D-1, D)')]
final class DeadlineRemindCommand extends Command
{
    public function __construct(
        private readonly DeadlineNotifier $deadlines,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->deadlines->remind() as ['deadline' => $deadline, 'stage' => $stage]) {
            $output->writeln(\sprintf('Reminder D-%d: %s', $stage->value, $deadline->getLabel()));
        }

        return Command::SUCCESS;
    }
}
