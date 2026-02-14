<?php

namespace App\Command;

use App\Service\CalendarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calendar:sync-holidays',
    description: 'Sync Japanese public holidays into calendar_holiday table.'
)]
class SyncJapaneseHolidaysCommand extends Command
{
    public function __construct(
        private CalendarService $calendarService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('year', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Year to sync (repeatable).')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start year (inclusive).')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End year (inclusive).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $years = $input->getOption('year');
        $from = $input->getOption('from');
        $to = $input->getOption('to');

        if ($from !== null || $to !== null) {
            $fromYear = $from !== null ? (int) $from : (int) date('Y');
            $toYear = $to !== null ? (int) $to : $fromYear;

            if ($fromYear > $toYear) {
                $io->error('The --from year must be less than or equal to --to year.');
                return Command::INVALID;
            }

            $years = range($fromYear, $toYear);
        }

        if ($years === []) {
            $years = [(int) date('Y')];
        }

        $total = 0;
        foreach ($years as $year) {
            $count = $this->calendarService->syncJapaneseHolidays((int) $year);
            $io->success(sprintf('Synced %d holidays for %d.', $count, $year));
            $total += $count;
        }

        $io->writeln(sprintf('Total holidays processed: %d', $total));

        return Command::SUCCESS;
    }
}
