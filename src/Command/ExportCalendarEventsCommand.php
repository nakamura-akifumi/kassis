<?php

namespace App\Command;

use App\Repository\CalendarEventRepository;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export:calendar-events',
    description: '休館日カレンダーをiCal(ICS)でエクスポートします。',
)]
class ExportCalendarEventsCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(private CalendarEventRepository $calendarEventRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('output', InputArgument::REQUIRED, '出力ファイルパス、または出力先ディレクトリ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $calendar = new VCalendar();
        $events = $this->calendarEventRepository->findBy([], ['dt_start' => 'ASC']);

        foreach ($events as $event) {
            $vevent = $calendar->add('VEVENT');
            $vevent->add('UID', $event->getUid());
            $vevent->add('SUMMARY', $event->getSummary());

            if ($event->getDescription()) {
                $vevent->add('DESCRIPTION', $event->getDescription());
            }
            if ($event->getLocation()) {
                $vevent->add('LOCATION', $event->getLocation());
            }
            if ($event->getStatus()) {
                $vevent->add('STATUS', $event->getStatus());
            }
            if ($event->getTransparency()) {
                $vevent->add('TRANSP', $event->getTransparency());
            }
            if ($event->getOrganizer()) {
                $vevent->add('ORGANIZER', $event->getOrganizer());
            }
            if ($event->getSequence() !== null) {
                $vevent->add('SEQUENCE', $event->getSequence());
            }
            if ($event->getRrule()) {
                $vevent->add('RRULE', $event->getRrule());
            }
            if ($event->getRdate()) {
                $vevent->add('RDATE', $event->getRdate());
            }
            if ($event->getExdate()) {
                $vevent->add('EXDATE', $event->getExdate());
            }
            if ($event->getRecurrenceId()) {
                $vevent->add('RECURRENCE-ID', $event->getRecurrenceId());
            }

            $dtStart = $event->getDtStart();
            $dtEnd = $event->getDtEnd();

            if ($event->isAllDay()) {
                $startDate = \DateTimeImmutable::createFromInterface($dtStart);
                $endDate = $dtEnd ? \DateTimeImmutable::createFromInterface($dtEnd) : $startDate;
                $exclusiveEnd = $endDate->modify('+1 day');

                $vevent->add('DTSTART', $startDate, ['VALUE' => 'DATE']);
                $vevent->add('DTEND', $exclusiveEnd, ['VALUE' => 'DATE']);
            } else {
                $vevent->add('DTSTART', $dtStart);
                if ($dtEnd) {
                    $vevent->add('DTEND', $dtEnd);
                }
                if ($event->getTimezone()) {
                    $vevent->DTSTART['TZID'] = $event->getTimezone();
                    if (isset($vevent->DTEND)) {
                        $vevent->DTEND['TZID'] = $event->getTimezone();
                    }
                }
            }

            $vevent->add('X-KASSIS-IS-CLOSED', $event->isClosed() ? 'TRUE' : 'FALSE');
        }

        try {
            $defaultName = 'calendar-events.ics';
            $outputPath = $this->resolveOutputPath((string) $input->getArgument('output'), $defaultName);
            $this->ensureDirectoryExists($outputPath);
            if (file_put_contents($outputPath, $calendar->serialize()) === false) {
                throw new RuntimeException('出力ファイルの保存に失敗しました。');
            }
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('エクスポートが完了しました。');
        return Command::SUCCESS;
    }
}
