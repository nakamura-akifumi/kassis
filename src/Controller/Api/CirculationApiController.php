<?php

namespace App\Controller\Api;

use App\Service\CirculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/circulation')]
final class CirculationApiController extends AbstractController
{
    #[Route('/reserve', name: 'api_circulation_reserve', methods: ['POST'])]
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

    #[Route('/checkout', name: 'api_circulation_checkout', methods: ['POST'])]
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

    #[Route('/checkin', name: 'api_circulation_checkin', methods: ['POST'])]
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
}
