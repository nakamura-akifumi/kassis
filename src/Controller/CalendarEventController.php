<?php

namespace App\Controller;

use App\Entity\CalendarEvent;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings/calendar-events')]
final class CalendarEventController extends AbstractController
{
    #[Route('', name: 'app_settings_calendar_events', methods: ['GET'])]
    public function index(CalendarEventRepository $calendarEventRepository): Response
    {
        $events = $calendarEventRepository->findBy([], ['dt_start' => 'DESC']);

        return $this->render('settings/calendar_event/index.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/new', name: 'app_settings_calendar_events_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new CalendarEvent();
        $event->setUid($this->generateUid());
        $event->setIsClosed(true);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('calendar_event_create', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', '不正なリクエストです。');
                return $this->redirectToRoute('app_settings_calendar_events');
            }

            $errors = $this->applyFormData($request, $event);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            } else {
                $entityManager->persist($event);
                $entityManager->flush();

                $this->addFlash('success', 'イベントを作成しました。');
                return $this->redirectToRoute('app_settings_calendar_events');
            }
        }

        return $this->render('settings/calendar_event/new.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_settings_calendar_events_edit', methods: ['GET', 'POST'])]
    public function edit(CalendarEvent $event, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('calendar_event_edit_' . $event->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', '不正なリクエストです。');
                return $this->redirectToRoute('app_settings_calendar_events');
            }

            $errors = $this->applyFormData($request, $event);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'イベントを更新しました。');
                return $this->redirectToRoute('app_settings_calendar_events');
            }
        }

        return $this->render('settings/calendar_event/edit.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_settings_calendar_events_delete', methods: ['POST'])]
    public function delete(CalendarEvent $event, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('calendar_event_delete_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_calendar_events');
        }

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'イベントを削除しました。');
        return $this->redirectToRoute('app_settings_calendar_events');
    }

    #[Route('/export', name: 'app_settings_calendar_events_export', methods: ['GET'])]
    public function export(CalendarEventRepository $calendarEventRepository): Response
    {
        $calendar = new VCalendar();
        $events = $calendarEventRepository->findBy([], ['dt_start' => 'ASC']);

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

        $response = new Response($calendar->serialize());
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendar-events.ics"');

        return $response;
    }

    #[Route('/import', name: 'app_settings_calendar_events_import', methods: ['POST'])]
    public function import(
        Request $request,
        CalendarEventRepository $calendarEventRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('calendar_event_import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_calendar_events');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('ics_file');
        if ($file === null) {
            $this->addFlash('danger', 'iCalファイルを選択してください。');
            return $this->redirectToRoute('app_settings_calendar_events');
        }

        if (strtolower((string) $file->getClientOriginalExtension()) !== 'ics') {
            $this->addFlash('danger', 'iCal (.ics) ファイルを指定してください。');
            return $this->redirectToRoute('app_settings_calendar_events');
        }

        try {
            $raw = file_get_contents($file->getPathname());
            $calendar = Reader::read($raw === false ? '' : $raw);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'iCalの解析に失敗しました。');
            return $this->redirectToRoute('app_settings_calendar_events');
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

            $event = $calendarEventRepository->findOneBy([
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
                $entityManager->persist($event);
                $created++;
            } else {
                $updated++;
            }
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('iCalを取り込みました。（新規: %d, 更新: %d）', $created, $updated));
        return $this->redirectToRoute('app_settings_calendar_events');
    }

    private function generateUid(): string
    {
        return bin2hex(random_bytes(16)) . '@kassis';
    }

    /**
     * @return string[]
     */
    private function applyFormData(Request $request, CalendarEvent $event): array
    {
        $errors = [];

        $summary = trim((string) $request->request->get('summary'));
        if ($summary === '') {
            $errors[] = '件名は必須です。';
        }

        $description = trim((string) $request->request->get('description'));
        $location = trim((string) $request->request->get('location'));
        $timezone = trim((string) $request->request->get('timezone'));
        $isClosed = $request->request->get('is_closed') === '1';
        $allDay = $request->request->get('all_day') === '1';

        $dtStart = null;
        $dtEnd = null;

        if ($allDay) {
            $startDate = trim((string) $request->request->get('start_date'));
            $endDate = trim((string) $request->request->get('end_date'));
            if ($startDate === '') {
                $errors[] = '開始日は必須です。';
            } else {
                $dtStart = \DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
                if ($dtStart === false) {
                    $errors[] = '開始日が不正です。';
                } else {
                    $dtStart = $dtStart->setTime(0, 0, 0);
                }
            }

            if ($endDate === '') {
                $endDate = $startDate;
            }

            if ($endDate !== '') {
                $dtEnd = \DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
                if ($dtEnd === false) {
                    $errors[] = '終了日が不正です。';
                } else {
                    $dtEnd = $dtEnd->setTime(23, 59, 59);
                }
            }
        } else {
            $startDateTime = trim((string) $request->request->get('start_datetime'));
            $endDateTime = trim((string) $request->request->get('end_datetime'));

            if ($startDateTime === '') {
                $errors[] = '開始日時は必須です。';
            } else {
                $dtStart = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startDateTime);
                if ($dtStart === false) {
                    $errors[] = '開始日時が不正です。';
                }
            }

            if ($endDateTime !== '') {
                $dtEnd = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endDateTime);
                if ($dtEnd === false) {
                    $errors[] = '終了日時が不正です。';
                }
            }
        }

        if ($dtStart !== null && $dtEnd !== null && $dtEnd < $dtStart) {
            $errors[] = '終了は開始以降にしてください。';
        }

        $recurrenceId = null;
        $recurrenceRaw = trim((string) $request->request->get('recurrence_id'));
        if ($recurrenceRaw !== '') {
            $recurrenceId = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $recurrenceRaw);
            if ($recurrenceId === false) {
                $errors[] = 'RECURRENCE-IDが不正です。';
            }
        }

        $sequence = trim((string) $request->request->get('sequence'));
        $sequenceValue = null;
        if ($sequence !== '') {
            if (!ctype_digit($sequence)) {
                $errors[] = 'SEQUENCEは整数で入力してください。';
            } else {
                $sequenceValue = (int) $sequence;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $event
            ->setSummary($summary)
            ->setDescription($description !== '' ? $description : null)
            ->setLocation($location !== '' ? $location : null)
            ->setDtStart($dtStart)
            ->setDtEnd($dtEnd)
            ->setAllDay($allDay)
            ->setTimezone($timezone !== '' ? $timezone : null)
            ->setStatus($this->normalizeOptional((string) $request->request->get('status')))
            ->setTransparency($this->normalizeOptional((string) $request->request->get('transparency')))
            ->setOrganizer($this->normalizeOptional((string) $request->request->get('organizer')))
            ->setSequence($sequenceValue)
            ->setRrule($this->normalizeOptional((string) $request->request->get('rrule')))
            ->setRdate($this->normalizeOptional((string) $request->request->get('rdate')))
            ->setExdate($this->normalizeOptional((string) $request->request->get('exdate')))
            ->setRecurrenceId($recurrenceId)
            ->setIsClosed($isClosed);

        return [];
    }

    private function normalizeOptional(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
