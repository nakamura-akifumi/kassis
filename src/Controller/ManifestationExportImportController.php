<?php

namespace App\Controller;

use App\Form\ManifestationFileExportFormType;
use App\Form\ManifestationFileImportFormType;
use App\Repository\ManifestationRepository;
use App\Service\FileService;
use App\Service\ManifestationFileColumns;
use App\Service\ManifestationSearchQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManifestationExportImportController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/file/import', name: 'app_manifestation_file_import', methods: ['GET', 'POST'])]
    public function import(Request $request, FileService $fileService): Response
    {
        $form = $this->createForm(ManifestationFileImportFormType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadFile = $form->get('uploadFile')->getData();
            $defaultStatus = $form->has('defaultStatus') ? $form->get('defaultStatus')->getData() : null;

            if ($uploadFile) {
                $result = $fileService->importManifestationsFromFile($uploadFile, $defaultStatus);

                if ($result['errors'] === 0 && $result['success'] > 0) {
                    $this->addFlash('success', sprintf('インポート完了: %d件（スキップ: %d件）', $result['success'], $result['skipped']));
                } elseif ($result['errors'] > 0) {
                    $this->addFlash('danger', 'インポート中にエラーが発生しました。結果を確認してください。');
                } else {
                    $this->addFlash('warning', 'インポート対象がありませんでした。');
                }
            } else {
                $this->addFlash('warning', 'ファイルを選択してください。');
            }
        }

        return $this->render('import/file.html.twig', [
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }

    #[Route('/file/export', name: 'app_manifestation_file_export', methods: ['GET', 'POST'])]
    public function export(Request $request, ManifestationRepository $manifestationRepository, FileService $fileService): Response
    {
        $form = $this->createForm(ManifestationFileExportFormType::class);
        $form->handleRequest($request);

        $isAjax = $request->isXmlHttpRequest();

        // GET: まず画面表示（検索条件があればそのまま引き継ぐ）
        if (!$form->isSubmitted()) {
            return $this->render('export/file.html.twig', [
                'form' => $form->createView(),
                'query' => $request->query->all(),
                'exportFields' => ManifestationFileColumns::getExportFormFields(),
                'requiredColumns' => ManifestationFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }

        // バリデーションNG（AJAXならJSONで返す）
        if (!$form->isValid()) {
            if ($isAjax) {
                $errors = [];
                foreach ($form->getErrors(true, true) as $error) {
                    $errors[] = $error->getMessage();
                }

                return new JsonResponse([
                    'message' => '入力内容にエラーがあります。内容を確認してください。',
                    'errors' => $errors,
                ], 422);
            }

            return $this->render('export/file.html.twig', [
                'form' => $form->createView(),
                'query' => $request->query->all(),
                'exportFields' => ManifestationFileColumns::getExportFormFields(),
                'requiredColumns' => ManifestationFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }

        try {
            $this->logger->info('エクスポート処理を開始');

            $format = (string) $form->get('format')->getData(); // xlsx|csv

            // 一覧画面と同じ検索クエリクラスを使用して条件を構築
            $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());

            // リポジトリの統一された検索メソッドを呼び出す
            $manifestations = $manifestationRepository->searchByQuery($searchQuery);

            $columns = $form->get('columns')->getData();
            $tempFile = $fileService->generateExportFile($manifestations, $format, $columns);

            $fileNameParts = ['manifestations'];

            $baseName = implode('_', $fileNameParts) . '_' . date('Y-m-d_H-i-s');
            $fileName = $baseName . ($format === 'csv' ? '.csv' : '.xlsx');

            return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        } catch (\Throwable $e) {
            $this->logger->error('エクスポート処理でエラーが発生しました', [
                'error' => $e->getMessage(),
            ]);

            if ($isAjax) {
                return new JsonResponse([
                    'message' => 'エクスポート中にエラーが発生しました。時間をおいて再度お試しください。',
                ], 500);
            }

            $this->addFlash('danger', 'エクスポート中にエラーが発生しました。');
            return $this->render('export/file.html.twig', [
                'form' => $form->createView(),
                'query' => $request->query->all(),
                'exportFields' => ManifestationFileColumns::getExportFormFields(),
                'requiredColumns' => ManifestationFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }
    }
}
