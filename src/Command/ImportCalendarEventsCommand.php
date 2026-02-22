<?php

namespace App\Command;

use App\Entity\CalendarEvent;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:calendar-events',
    description: 'iCal(ICS)ファイルから休館日カレンダーをインポートします。',
)]
class ImportCalendarEventsCommand extends Command
{
    public function __construct(
        private CalendarEventRepository $calendarEventRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'インポートするICSファイルのパス');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('file');

        if ($path === '' || !is_file($path)) {
            $io->error('ファイルが見つかりません。');
            return Command::FAILURE;
        }

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'ics') {
            $io->error('iCal (.ics) ファイルを指定してください。');
            return Command::FAILURE;
        }

        try {
            $raw = file_get_contents($path);
            $calendar = Reader::read($raw === false ? '' : $raw);
        } catch (\Exception $e) {
            $io->error('iCalの解析に失敗しました。');
            return Command::FAILURE;
        }

        $created = 0;
        $updated = 0;
        foreach ($calendar->select('VEVENT') as $vevent) {
            $uid = isset($vevent->UID) ? trim((string) $vevent->UID) : '';
            if ($uid === '') {
                $uid = $this->generateUid();
            }

            $recurrenceId = null;
            if (isset($vevent->{'RECURRENCE-ID'})) {
                $recurrenceId = $vevent->{'RECURRENCE-ID'}->getDateTime();
            }

            $event = $this->calendarEventRepository->findOneBy([
                'uid' => $uid,
                'recurrence_id' => $recurrenceId,
            ]);

            $isNew = false;
            if ($event === null) {
                $event = new CalendarEvent();
                $event->setUid($uid);
                $isNew = true;
            }

            $dtStartProp = $vevent->DTSTART ?? null;
            if ($dtStartProp === null) {
                continue;
            }

            $allDay = $dtStartProp->getValueType() === 'DATE';
            $dtStart = $dtStartProp->getDateTime();
            $dtEnd = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : null;

            if ($allDay) {
                if ($dtEnd !== null) {
                    $dtEnd = (\DateTimeImmutable::createFromInterface($dtEnd))
                        ->modify('-1 day')
                        ->setTime(23, 59, 59);
                } else {
                    $dtEnd = (\DateTimeImmutable::createFromInterface($dtStart))
                        ->setTime(23, 59, 59);
                }
                $dtStart = (\DateTimeImmutable::createFromInterface($dtStart))
                    ->setTime(0, 0, 0);
            }

            $timezone = null;
            if (isset($dtStartProp['TZID'])) {
                $timezone = (string) $dtStartProp['TZID'];
            }

            $isClosed = true;
            if (isset($vevent->{'X-KASSIS-IS-CLOSED'})) {
                $isClosed = strtoupper((string) $vevent->{'X-KASSIS-IS-CLOSED'}) === 'TRUE';
            }

            $event
                ->setSummary((string) ($vevent->SUMMARY ?? ''))
                ->setDescription(isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null)
                ->setLocation(isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null)
                ->setDtStart($dtStart)
                ->setDtEnd($dtEnd)
                ->setAllDay($allDay)
                ->setTimezone($timezone)
                ->setRecurrenceId($recurrenceId)
                ->setRrule(isset($vevent->RRULE) ? (string) $vevent->RRULE : null)
                ->setRdate(isset($vevent->RDATE) ? (string) $vevent->RDATE : null)
                ->setExdate(isset($vevent->EXDATE) ? (string) $vevent->EXDATE : null)
                ->setStatus(isset($vevent->STATUS) ? (string) $vevent->STATUS : null)
                ->setTransparency(isset($vevent->TRANSP) ? (string) $vevent->TRANSP : null)
                ->setOrganizer(isset($vevent->ORGANIZER) ? (string) $vevent->ORGANIZER : null)
                ->setSequence(isset($vevent->SEQUENCE) ? (int) (string) $vevent->SEQUENCE : null)
                ->setIsClosed($isClosed);

            if ($isNew) {
                $this->entityManager->persist($event);
                $created++;
            } else {
                $updated++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('iCalを取り込みました。（新規: %d, 更新: %d）', $created, $updated));
        return Command::SUCCESS;
    }

    private function generateUid(): string
    {
        return bin2hex(random_bytes(16)) . '@kassis';
    }
}
