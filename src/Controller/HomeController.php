<?php

namespace App\Controller;

use App\Repository\ManifestationRepository;
use App\Repository\CodeRepository;
use App\Service\ManifestationSearchQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, ManifestationRepository $manifestationRepository, ParameterBagInterface $params, CodeRepository $codeRepository): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);
        $limits = (array) $params->get('app.manifestation.search_limits');
        $viewMode = $request->query->get('view_mode', 'list') ?: 'list';
        $useType1Code = $params->has('app.manifestation.type1.use_code') && (bool) $params->get('app.manifestation.type1.use_code');
        $useType2Code = $params->has('app.manifestation.type2.use_code') && (bool) $params->get('app.manifestation.type2.use_code');
        $type1Choices = $useType1Code ? $this->getCodeChoices($codeRepository, 'manifestation_type1') : [];
        $type2Choices = $useType2Code ? $this->getCodeChoices($codeRepository, 'manifestation_type2') : [];

        return $this->render('manifestation/index.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
            'display_limits' => $limits,
            'view_mode' => $viewMode,
            'use_type1_code' => $useType1Code,
            'use_type2_code' => $useType2Code,
            'type1_choices' => $type1Choices,
            'type2_choices' => $type2Choices,
        ]);

    }

    /**
     * @return array<string, string>
     */
    private function getCodeChoices(CodeRepository $codeRepository, string $type): array
    {
        $choices = [];
        $codes = $codeRepository->findBy(['type' => $type], ['display_order' => 'ASC', 'identifier' => 'ASC']);
        foreach ($codes as $code) {
            $label = $code->getDisplayname();
            if ($label === null || trim($label) === '') {
                $label = $code->getIdentifier();
            }
            $choices[$label] = $code->getIdentifier();
        }

        return $choices;
    }
}
