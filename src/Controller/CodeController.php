<?php

namespace App\Controller;

use App\Entity\Code;
use App\Repository\CodeRepository;
use App\Service\CodeFileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/settings/code')]
class CodeController extends AbstractController
{
    #[Route('', name: 'app_settings_code', methods: ['GET'])]
    public function index(CodeRepository $codeRepository, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        $codes = $codeRepository->findBy([], ['type' => 'ASC', 'display_order' => 'ASC', 'identifier' => 'ASC']);
        $gridData = array_map(static function (Code $code): array {
            return [
                'type' => $code->getType(),
                'identifier' => $code->getIdentifier(),
                'value' => $code->getValue(),
                'displayname' => $code->getDisplayname(),
                'displayOrder' => $code->getDisplayOrder(),
                'updatedAt' => $code->getUpdatedAt()?->format('Y-m-d H:i'),
            ];
        }, $codes);

        $typeOptions = $params->has('app.code.types') ? $params->get('app.code.types') : [];

        return $this->render('settings/code.html.twig', [
            'codes' => $codes,
            'gridData' => $gridData,
            'typeOptions' => $typeOptions,
        ]);
    }

    #[Route('/file', name: 'app_settings_code_file', methods: ['GET'])]
    public function filePage(): Response
    {
        return $this->render('settings/code_file.html.twig');
    }

    #[Route('/export', name: 'app_settings_code_export', methods: ['GET'])]
    public function export(Request $request, CodeRepository $codeRepository, CodeFileService $codeFileService): Response
    {
        $format = (string) $request->query->get('format', 'xlsx');
        if (!in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        $codes = $codeRepository->findBy([], ['type' => 'ASC', 'display_order' => 'ASC', 'identifier' => 'ASC']);
        $tempFile = $codeFileService->generateExportFile($codes, $format);
        $fileName = 'codes_' . date('Y-m-d_H-i-s') . ($format === 'csv' ? '.csv' : '.xlsx');

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/import', name: 'app_settings_code_import', methods: ['POST'])]
    public function import(Request $request, CodeFileService $codeFileService, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        if (!$this->isCsrfTokenValid('code_import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        $uploadFile = $request->files->get('uploadFile');
        if ($uploadFile === null) {
            $this->addFlash('danger', 'ファイルを選択してください。');
            return $this->redirectToRoute('app_settings_code');
        }

        $typeOptions = $params->has('app.code.types') ? (array) $params->get('app.code.types') : [];
        $result = $codeFileService->importCodesFromFile($uploadFile, $typeOptions);

        if ($result['errors'] > 0) {
            $this->addFlash('danger', 'インポート中にエラーが発生しました。結果を確認してください。');
            foreach ($result['errorMessages'] as $message) {
                $this->addFlash('danger', $message);
            }
        } elseif ($result['created'] > 0 || $result['updated'] > 0) {
            $this->addFlash('success', sprintf('インポート完了: 新規 %d件 / 更新 %d件（スキップ: %d件）', $result['created'], $result['updated'], $result['skipped']));
        } else {
            $this->addFlash('warning', 'インポート対象がありませんでした。');
        }

        return $this->redirectToRoute('app_settings_code');
    }

    #[Route('/create', name: 'app_settings_code_create', methods: ['POST'])]
    public function create(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));
        $value = $request->request->get('value');
        $displayname = trim((string) $request->request->get('displayname'));
        $displayOrder = (int) $request->request->get('display_order', 0);

        if (!$this->isCsrfTokenValid('code_create', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($type === '' || $identifier === '') {
            $this->addFlash('danger', '種別と識別子は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }
        $typeOptions = $params->has('app.code.types') ? $params->get('app.code.types') : [];
        if ($typeOptions !== [] && !in_array($type, $typeOptions, true)) {
            $this->addFlash('danger', '種別が不正です。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($value === null || $value === '') {
            $this->addFlash('danger', '値は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]) !== null) {
            $this->addFlash('danger', '同じ種別と識別子のコードが既に存在します。');
            return $this->redirectToRoute('app_settings_code');
        }

        $code = new Code();
        $code->setType($type);
        $code->setIdentifier($identifier);
        $code->setValue((string) $value);
        $code->setDisplayname($displayname !== '' ? $displayname : null);
        $code->setDisplayOrder($displayOrder);

        $entityManager->persist($code);
        $entityManager->flush();

        $this->addFlash('success', 'コードを追加しました。');
        return $this->redirectToRoute('app_settings_code');
    }

    #[Route('/update', name: 'app_settings_code_update', methods: ['POST'])]
    public function update(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        if (!$this->isCsrfTokenValid('code_update', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));
        $value = $request->request->get('value');
        $displayname = trim((string) $request->request->get('displayname'));
        $displayOrder = (int) $request->request->get('display_order', 0);

        $code = $codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]);
        if ($code === null) {
            $this->addFlash('danger', '対象のコードが見つかりません。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($value === null || $value === '') {
            $this->addFlash('danger', '値は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }

        $code->setValue((string) $value);
        $code->setDisplayname($displayname !== '' ? $displayname : null);
        $code->setDisplayOrder($displayOrder);

        $entityManager->flush();

        $this->addFlash('success', 'コードを更新しました。');
        return $this->redirectToRoute('app_settings_code');
    }

    #[Route('/delete', name: 'app_settings_code_delete', methods: ['POST'])]
    public function delete(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('code_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));

        $code = $codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]);
        if ($code === null) {
            $this->addFlash('danger', '対象のコードが見つかりません。');
            return $this->redirectToRoute('app_settings_code');
        }

        $entityManager->remove($code);
        $entityManager->flush();

        $this->addFlash('success', 'コードを削除しました。');
        return $this->redirectToRoute('app_settings_code');
    }
}
