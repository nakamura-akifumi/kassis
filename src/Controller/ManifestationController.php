<?php

namespace App\Controller;

use App\Entity\Manifestation;
use App\Entity\ManifestationAttachment;
use App\Form\AttachmentUploadFormType;
use App\Form\ManifestationType;
use App\Repository\CodeRepository;
use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/manifestation')]
final class ManifestationController extends AbstractController
{
    #[Route('/manifestation_search', name: 'app_manifestation_search', methods: ['GET'])]
    public function search(Request $request, ManifestationRepository $manifestationRepository, ParameterBagInterface $params): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);
        $totalCount = count($manifestations);
        $viewMode = $request->query->get('view_mode', 'list') ?: 'list';
        $limits = (array) $params->get('app.manifestation.search_limits');

        if ($request->isXmlHttpRequest() && $viewMode === 'grid') {
            $gridLimit = (int) ($limits['grid'] ?? 0);
            if ($gridLimit > 0) {
                $manifestations = array_slice($manifestations, 0, $gridLimit);
            }
            $data = [];
            foreach ($manifestations as $m) {
                $data[] = [
                    'id' => $m->getId(),
                    'title' => $m->getTitle(),
                    'identifier' => $m->getIdentifier(),
                    'externalIdentifier1' => $m->getExternalIdentifier1(),
                    'purchaseDate' => $m->getPurchaseDate()?->format('Y-m-d'),
                ];
            }
            $response = $this->json($data);
            $response->headers->set('X-Result-Count', (string) $totalCount);
            return $response;
        }

        $response = $this->render('manifestation/search.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
            'view_mode' => $viewMode,
            'display_limits' => $limits,
        ]);
        $response->headers->set('X-Result-Count', (string) $totalCount);
        return $response;
    }

    #[Route('/manifestation', name: 'app_manifestation_index', methods: ['GET'])]
    public function index(Request $request, ManifestationRepository $manifestationRepository, ParameterBagInterface $params, CodeRepository $codeRepository): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);
        $viewMode = $request->query->get('view_mode', 'list');
        if ($viewMode === null) {
            $viewMode = 'list';
        }
        $limits = (array) $params->get('app.manifestation.search_limits');
        $useType1Code = $params->has('app.manifestation.type1.use_code') && (bool) $params->get('app.manifestation.type1.use_code');
        $useType2Code = $params->has('app.manifestation.type2.use_code') && (bool) $params->get('app.manifestation.type2.use_code');
        $type1Choices = $useType1Code ? $this->getCodeChoices($codeRepository, 'manifestation_type1') : [];
        $type2Choices = $useType2Code ? $this->getCodeChoices($codeRepository, 'manifestation_type2') : [];

        return $this->render('manifestation/index.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
            'view_mode' => $viewMode,
            'display_limits' => $limits,
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

    #[Route('/new', name: 'app_manifestation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $manifestation = new Manifestation();
        $form = $this->createForm(ManifestationType::class, $manifestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($manifestation);
            $entityManager->flush();

            return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/new.html.twig', [
            'manifestation' => $manifestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_manifestation_show', methods: ['GET', 'POST'])]
    public function show(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager, SluggerInterface $slugger, CodeRepository $codeRepository, ParameterBagInterface $params): Response
    {
        $form = $this->createForm(AttachmentUploadFormType::class);
        $form->handleRequest($request);

        $type1Display = $manifestation->getType1();
        if ($params->has('app.manifestation.type1.use_code') && (bool) $params->get('app.manifestation.type1.use_code')) {
            $type1Identifier = is_string($type1Display) ? trim($type1Display) : null;
            if ($type1Identifier !== null && $type1Identifier !== '') {
                $code = $codeRepository->findOneBy([
                    'type' => 'manifestation_type1',
                    'identifier' => $type1Identifier,
                ]);
                $displayName = $code?->getDisplayname();
                if ($displayName !== null && trim($displayName) !== '') {
                    $type1Display = $displayName;
                }
            }
        }

        $type2Display = $manifestation->getType2();
        if ($params->has('app.manifestation.type2.use_code') && (bool) $params->get('app.manifestation.type2.use_code')) {
            $type2Identifier = is_string($type2Display) ? trim($type2Display) : null;
            if ($type2Identifier !== null && $type2Identifier !== '') {
                $code = $codeRepository->findOneBy([
                    'type' => 'manifestation_type2',
                    'identifier' => $type2Identifier,
                ]);
                $displayName = $code?->getDisplayname();
                if ($displayName !== null && trim($displayName) !== '') {
                    $type2Display = $displayName;
                }
            }
        }

        $type3Display = $manifestation->getType3();
        if ($params->has('app.manifestation.type3.use_code') && (bool) $params->get('app.manifestation.type3.use_code')) {
            $type3Identifier = is_string($type3Display) ? trim($type3Display) : null;
            if ($type3Identifier !== null && $type3Identifier !== '') {
                $code = $codeRepository->findOneBy([
                    'type' => 'manifestation_type3',
                    'identifier' => $type3Identifier,
                ]);
                $displayName = $code?->getDisplayname();
                if ($displayName !== null && trim($displayName) !== '') {
                    $type3Display = $displayName;
                }
            }
        }

        $type4Display = $manifestation->getType4();
        if ($params->has('app.manifestation.type4.use_code') && (bool) $params->get('app.manifestation.type4.use_code')) {
            $type4Identifier = is_string($type4Display) ? trim($type4Display) : null;
            if ($type4Identifier !== null && $type4Identifier !== '') {
                $code = $codeRepository->findOneBy([
                    'type' => 'manifestation_type4',
                    'identifier' => $type4Identifier,
                ]);
                $displayName = $code?->getDisplayname();
                if ($displayName !== null && trim($displayName) !== '') {
                    $type4Display = $displayName;
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $attachmentFile = $form->get('attachment')->getData();

            if ($attachmentFile) {
                $originalFilename = pathinfo($attachmentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $attachmentFile->guessExtension();

                // ファイルを移動する前に情報を取得する
                $fileSize = $attachmentFile->getSize();
                $mimeType = $attachmentFile->getMimeType();

                try {
                    $attachmentFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/attachments',
                        $newFilename
                    );

                    $attachment = new ManifestationAttachment();
                    $attachment->setManifestation($manifestation);
                    $attachment->setFileName($attachmentFile->getClientOriginalName());
                    $attachment->setFilePath('uploads/attachments/' . $newFilename);
                    $attachment->setFileSize($fileSize);
                    $attachment->setMimeType($mimeType);

                    $entityManager->persist($attachment);
                    $entityManager->flush();

                    $this->addFlash('success', 'ファイルを添付しました。');
                } catch (FileException $e) {
                    $this->addFlash('error', 'ファイルのアップロードに失敗しました。');
                }
            }

            return $this->redirectToRoute('app_manifestation_show', ['id' => $manifestation->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/show.html.twig', [
            'manifestation' => $manifestation,
            'attachment_form' => $form->createView(),
            'type1Display' => $type1Display,
            'type2Display' => $type2Display,
            'type3Display' => $type3Display,
            'type4Display' => $type4Display,
        ]);
    }

    #[Route('/attachment/{id}/delete', name: 'app_manifestation_attachment_delete', methods: ['POST'])]
    public function deleteAttachment(Request $request, ManifestationAttachment $attachment, EntityManagerInterface $entityManager): Response
    {
        $manifestationId = $attachment->getManifestation()->getId();

        if ($this->isCsrfTokenValid('delete' . $attachment->getId(), $request->request->get('_token'))) {
            // ファイルの削除
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $attachment->getFilePath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $entityManager->remove($attachment);
            $entityManager->flush();
            $this->addFlash('success', '添付ファイルを削除しました。');
        }

        return $this->redirectToRoute('app_manifestation_show', ['id' => $manifestationId]);
    }

    #[Route('/{id}/edit', name: 'app_manifestation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ManifestationType::class, $manifestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/edit.html.twig', [
            'manifestation' => $manifestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/popup', name: 'app_manifestation_popup', methods: ['GET'])]
    public function popup(Manifestation $manifestation): JsonResponse
    {
        return $this->json([
            'id' => $manifestation->getId(),
            'title' => $manifestation->getTitle(),
            'titleTranscription' => $manifestation->getTitleTranscription(),
            'identifier' => $manifestation->getIdentifier(),
            'externalIdentifier1' => $manifestation->getExternalIdentifier1(),
            'externalIdentifier2' => $manifestation->getExternalIdentifier2(),
            'externalIdentifier3' => $manifestation->getExternalIdentifier3(),
            'purchaseDate' => $manifestation->getPurchaseDate()?->format('Y-m-d'),
            'buyer' => $manifestation->getBuyer(),
            'buyerIdentifier' => $manifestation->getBuyerIdentifier(),
            'type1' => $manifestation->getType1(),
            'type2' => $manifestation->getType2(),
            'location1' => $manifestation->getLocation1(),
            'location2' => $manifestation->getLocation2(),
            'location3' => $manifestation->getLocation3(),
            'contributor1' => $manifestation->getContributor1(),
            'contributor2' => $manifestation->getContributor2(),
            'releaseDateString' => $manifestation->getReleaseDateString(),
            'price' => $manifestation->getFormattedPrice(),
            'priceCurrency' => $manifestation->getPriceCurrency(),
            'description' => $manifestation->getDescription(),
            'status1' => $manifestation->getStatus1(),
            'status2' => $manifestation->getStatus2(),
            'updatedAt' => $manifestation->getUpdatedAt()?->format('Y-m-d H:i'),
        ]);
    }

    #[Route('/{id}/popup_view', name: 'app_manifestation_popup_view', methods: ['GET'])]
    public function popupView(Manifestation $manifestation): Response
    {
        return $this->render('manifestation/_popup_detail.html.twig', [
            'manifestation' => $manifestation,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_manifestation_delete', methods: ['POST'])]
    public function delete(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $manifestation->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($manifestation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
    }
}
