<?php

namespace App\Controller\Api;

use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/manifestations')]
final class ManifestationApiController extends AbstractController
{
    #[Route('', name: 'api_manifestation_search', methods: ['GET'])]
    public function search(Request $request, ManifestationRepository $manifestationRepository): JsonResponse
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);

        $data = [];
        foreach ($manifestations as $m) {
            $data[] = [
                'id' => $m->getId(),
                'title' => $m->getTitle(),
                'identifier' => $m->getIdentifier(),
                'externalIdentifier1' => $m->getExternalIdentifier1(),
                'type1' => $m->getType1(),
                'type2' => $m->getType2(),
                'type3' => $m->getType3(),
                'type4' => $m->getType4(),
                'location1' => $m->getLocation1(),
                'location2' => $m->getLocation2(),
                'status1' => $m->getStatus1(),
                'createdAt' => $m->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $response = $this->json([
            'items' => $data,
            'count' => count($data),
        ]);

        return $response;
    }
}
