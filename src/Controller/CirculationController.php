<?php

namespace App\Controller;

use App\Service\CirculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CirculationController extends AbstractController
{
    #[Route('/circulation', name: 'app_circulation_index', methods: ['GET'])]
    public function index(Request $request, \App\Repository\ReservationRepository $reservationRepository, \App\Repository\CheckoutRepository $checkoutRepository): Response
    {
        $type = $this->resolveStatusType($request, 'checkout');
        [$reservations, $checkouts] = $this->getStatusData($type, $reservationRepository, $checkoutRepository);

        return $this->render('circulation/index.html.twig', [
            'type' => $type,
            'reservations' => $reservations,
            'checkouts' => $checkouts,
        ]);
    }

    #[Route('/circulation/reserve', name: 'app_circulation_reserve_page', methods: ['GET'])]
    public function reservePage(Request $request): Response
    {
        return $this->render('circulation/reserve.html.twig', [
            'memberIdentifier' => $request->query->get('memberIdentifier'),
            'manifestationIdentifier' => $request->query->get('manifestationIdentifier'),
        ]);
    }

    #[Route('/circulation/checkout', name: 'app_circulation_checkout_page', methods: ['GET'])]
    public function checkoutPage(Request $request, ParameterBagInterface $params): Response
    {
        $dueDays = $params->has('app.checkout.due_days') ? $params->get('app.checkout.due_days') : null;
        $dueDays = is_numeric($dueDays) ? (int) $dueDays : null;
        $dueDate = null;
        if ($dueDays !== null && $dueDays > 0 && $dueDays !== 9999) {
            $dueDate = (new \DateTimeImmutable())->modify('+' . $dueDays . ' days')->format('Y-m-d');
        }

        return $this->render('circulation/checkout.html.twig', [
            'dueDate' => $dueDate,
            'dueDays' => $dueDays,
            'memberIdentifier' => $request->query->get('memberIdentifier'),
        ]);
    }

    #[Route('/circulation/checkin', name: 'app_circulation_checkin_page', methods: ['GET'])]
    public function checkInPage(): Response
    {
        return $this->render('circulation/checkin.html.twig');
    }


    #[Route('/circulation/reserve', name: 'app_circulation_reserve', methods: ['POST'])]
    public function reserve(Request $request, CirculationService $service): JsonResponse
    {
        $data = $this->getRequestData($request);
        $memberIdentifier = $data['memberIdentifier'] ?? null;
        $manifestationIdentifier = $data['manifestationIdentifier'] ?? null;
        $expiryDate = isset($data['expiryDate']) ? (int) $data['expiryDate'] : null;

        if (!$memberIdentifier || !$manifestationIdentifier) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        try {
            $reservation = $service->reserve($memberIdentifier, $manifestationIdentifier, $expiryDate);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'reservationId' => $reservation->getId(),
            'reservationStatus' => $reservation->getStatus(),
        ]);
    }

    #[Route('/circulation/checkout', name: 'app_circulation_checkout', methods: ['POST'])]
    public function checkout(Request $request, CirculationService $service): JsonResponse
    {
        $data = $this->getRequestData($request);
        $memberIdentifier = $data['memberIdentifier'] ?? null;
        $manifestationIdentifiers = $data['manifestationIdentifiers'] ?? null;

        if (!$memberIdentifier || !$manifestationIdentifiers) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $identifierList = is_array($manifestationIdentifiers)
            ? $manifestationIdentifiers
            : [$manifestationIdentifiers];

        try {
            $checkouts = $service->checkout($memberIdentifier, $identifierList);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'checkoutCount' => count($checkouts),
        ]);
    }

    #[Route('/circulation/checkin', name: 'app_circulation_checkin', methods: ['POST'])]
    public function checkIn(Request $request, CirculationService $service, TranslatorInterface $translator): JsonResponse
    {
        $data = $this->getRequestData($request);
        $manifestationIdentifier = $data['manifestationIdentifier'] ?? null;

        if (!$manifestationIdentifier) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        try {
            $checkout = $service->checkIn($manifestationIdentifier);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            if ($message === 'Manifestation not found.') {
                $message = $translator->trans('Model.Manifestation.not_found');
            }
            return new JsonResponse(['error' => $message], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'checkedIn' => $checkout !== null,
            'manifestationIdentifier' => $manifestationIdentifier,
            'memberIdentifier' => $checkout?->getMember()?->getIdentifier(),
            'checkedInAt' => $checkout?->getCheckedInAt()?->format('Y-m-d H:i'),
        ]);
    }

    #[Route('/circulation/export', name: 'app_circulation_export', methods: ['GET'])]
    public function exportStatus(Request $request, \App\Repository\ReservationRepository $reservationRepository, \App\Repository\CheckoutRepository $checkoutRepository, \App\Service\CirculationExportService $exportService): Response
    {
        $type = $this->resolveStatusType($request);
        [$reservations, $checkouts] = $this->getStatusData($type, $reservationRepository, $checkoutRepository);

        $tempFile = $exportService->generateStatusExportFile($type, $reservations, $checkouts);
        $fileName = sprintf('circulation_%s_%s.xlsx', $type, date('Y-m-d_H-i-s'));

        return $this->file($tempFile, $fileName);
    }

    private function getRequestData(Request $request): array
    {
        $data = [];
        if ($request->getContent() !== '') {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = [];
            }
        }

        if ($request->request->count() > 0) {
            $data = array_merge($data, $request->request->all());
        }

        return $data;
    }

    private function resolveStatusType(Request $request, string $default = 'reserve'): string
    {
        $type = (string) $request->query->get('type', $default);
        $allowed = ['reserve', 'checkout', 'return'];
        if (!in_array($type, $allowed, true)) {
            $type = $default;
        }

        return $type;
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function getStatusData(string $type, \App\Repository\ReservationRepository $reservationRepository, \App\Repository\CheckoutRepository $checkoutRepository): array
    {
        $reservations = [];
        $checkouts = [];

        if ($type === 'reserve') {
            $reservations = $reservationRepository->findRecent(50);
        } elseif ($type === 'checkout') {
            $checkouts = $checkoutRepository->findRecentActive(50);
        } else {
            $checkouts = $checkoutRepository->findRecentReturned(50);
        }

        return [$reservations, $checkouts];
    }
}
