<?php

namespace App\Controller;

use App\Repository\CodeRepository;
use App\Repository\LoanConditionRepository;
use App\Repository\LoanGroupRepository;
use App\Service\SettingsFileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/settings')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig');
    }

    #[Route('/export', name: 'app_settings_export', methods: ['GET'])]
    public function export(
        CodeRepository $codeRepository,
        LoanGroupRepository $loanGroupRepository,
        LoanConditionRepository $loanConditionRepository,
        SettingsFileService $fileService,
        TranslatorInterface $translator
    ): Response {
        $codes = $codeRepository->findBy([], ['type' => 'ASC', 'display_order' => 'ASC', 'identifier' => 'ASC']);
        $groups = $loanGroupRepository->findBy([], ['name' => 'ASC']);
        $conditions = $loanConditionRepository->findBy([], ['id' => 'DESC']);
        $type1Options = $this->getType1Options($codeRepository);
        $allGroupLabel = $this->getAllGroupLabel($translator);

        $tempFile = $fileService->generateExportFile($codes, $groups, $conditions, $type1Options, $allGroupLabel);
        $fileName = 'settings_' . date('Y-m-d_H-i-s') . '.xlsx';

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/import', name: 'app_settings_import', methods: ['POST'])]
    public function import(
        Request $request,
        SettingsFileService $fileService,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        CodeRepository $codeRepository
    ): Response {
        if (!$this->isCsrfTokenValid('settings_import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_index');
        }

        $uploadFile = $request->files->get('uploadFile');
        if ($uploadFile === null) {
            $this->addFlash('danger', 'ファイルを選択してください。');
            return $this->redirectToRoute('app_settings_index');
        }

        $type1Options = $this->getType1Options($codeRepository);
        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);
        $codeTypeOptions = $params->has('app.code.types') ? (array) $params->get('app.code.types') : [];
        $allGroupLabel = $this->getAllGroupLabel($translator);

        $result = $fileService->importFromFile($uploadFile, $type1Options, $memberGroupOptions, $codeTypeOptions, $allGroupLabel);

        if ($result['errors'] > 0) {
            $this->addFlash('danger', 'インポート中にエラーが発生しました。結果を確認してください。');
            foreach ($result['errorMessages'] as $message) {
                $this->addFlash('danger', $message);
            }
            foreach ($result['warningMessages'] as $message) {
                $this->addFlash('warning', $message);
            }
        } elseif (
            $result['createdCodes'] > 0 || $result['updatedCodes'] > 0
            || $result['createdGroups'] > 0 || $result['updatedGroups'] > 0
            || $result['createdConditions'] > 0 || $result['updatedConditions'] > 0
        ) {
            $this->addFlash('success', sprintf(
                'インポート完了: コード 新規 %d件 / 更新 %d件、貸出グループ 新規 %d件 / 更新 %d件、貸出条件 新規 %d件 / 更新 %d件（スキップ: %d件）',
                $result['createdCodes'],
                $result['updatedCodes'],
                $result['createdGroups'],
                $result['updatedGroups'],
                $result['createdConditions'],
                $result['updatedConditions'],
                $result['skipped']
            ));
            foreach ($result['warningMessages'] as $message) {
                $this->addFlash('warning', $message);
            }
        } else {
            $this->addFlash('warning', 'インポート対象がありませんでした。');
            foreach ($result['warningMessages'] as $message) {
                $this->addFlash('warning', $message);
            }
        }

        return $this->redirectToRoute('app_settings_index');
    }

    /**
     * @return array<string, string> identifier => label
     */
    private function getType1Options(CodeRepository $codeRepository): array
    {
        $codes = $codeRepository->findBy(['type' => 'manifestation_type1'], ['display_order' => 'ASC', 'identifier' => 'ASC']);
        $options = [];
        foreach ($codes as $code) {
            $label = $code->getDisplayname();
            if ($label === null || trim($label) === '') {
                $label = $code->getIdentifier();
            }
            $options[$code->getIdentifier()] = $label;
        }
        return $options;
    }

    private function getAllGroupLabel(TranslatorInterface $translator): string
    {
        return $translator->trans('Model.LoanGroup.values.all_group_members_identifier');
    }

    /**
     * @return array<string, string> label => value
     */
    private function buildMemberGroupChoices(ParameterBagInterface $params, TranslatorInterface $translator): array
    {
        $group1Choices = $params->has('app.member.group1') ? (array) $params->get('app.member.group1') : [];
        $group1Choices = array_values(array_filter($group1Choices, static fn($value) => trim((string) $value) !== ''));
        $choices = [];
        foreach ($group1Choices as $value) {
            $key = 'Model.Member.values.Group1.' . $value;
            $label = $translator->trans($key);
            if ($label === $key) {
                $label = $value;
            }
            $choices[$label] = $value;
        }
        return $choices;
    }
}
