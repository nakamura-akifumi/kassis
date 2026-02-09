<?php

namespace App\Controller;

use App\Entity\LoanGroup;
use App\Repository\CodeRepository;
use App\Repository\LoanGroupRepository;
use App\Repository\LoanGroupType1Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/settings/loan-group')]
final class LoanGroupController extends AbstractController
{
    #[Route('', name: 'app_settings_loan_group', methods: ['GET'])]
    public function index(LoanGroupRepository $loanGroupRepository, CodeRepository $codeRepository): Response
    {
        $groups = $loanGroupRepository->findBy([], ['name' => 'ASC']);
        $type1Options = $this->getType1Options($codeRepository);

        return $this->render('settings/loan_group/index.html.twig', [
            'groups' => $groups,
            'type1Options' => $type1Options,
        ]);
    }

    #[Route('/create', name: 'app_settings_loan_group_create', methods: ['POST'])]
    public function create(
        Request $request,
        LoanGroupType1Repository $mappingRepository,
        CodeRepository $codeRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ): Response {
        if (!$this->isCsrfTokenValid('loan_group_create', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('danger', '貸出グループ名は必須です。');
            return $this->redirectToRoute('app_settings_loan_group');
        }
        if ($name === $this->getAllGroupLabel($translator)) {
            $this->addFlash('danger', 'この貸出グループ名は使用できません。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $type1Options = $this->getType1Options($codeRepository);
        $selected = $this->normalizeType1Identifiers($request->request->all('type1_identifiers'));
        if (!$this->isValidType1Selection($selected, $type1Options)) {
            $this->addFlash('danger', '分類1の選択が不正です。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $conflicts = $mappingRepository->findConflicts($selected);
        if ($conflicts !== []) {
            foreach ($conflicts as $identifier => $groupName) {
                $this->addFlash('danger', sprintf('分類1 "%s" は既に貸出グループ "%s" に割り当て済みです。', $identifier, $groupName));
            }
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $group = new LoanGroup();
        $group->setName($name);
        $group->setType1Identifiers($selected);

        $entityManager->persist($group);
        $entityManager->flush();

        $this->addFlash('success', '貸出グループを追加しました。');
        return $this->redirectToRoute('app_settings_loan_group');
    }

    #[Route('/create-from-type1', name: 'app_settings_loan_group_create_from_type1', methods: ['POST'])]
    public function createFromType1(
        Request $request,
        LoanGroupType1Repository $mappingRepository,
        CodeRepository $codeRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ): Response {
        if (!$this->isCsrfTokenValid('loan_group_create_from_type1', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $type1Options = $this->getType1Options($codeRepository);
        if ($type1Options === []) {
            $this->addFlash('warning', '分類1が未登録のため作成できません。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $identifiers = array_keys($type1Options);
        $assigned = $mappingRepository->findConflicts($identifiers);

        $created = 0;
        $skippedAllGroup = 0;
        $allGroupLabel = $this->getAllGroupLabel($translator);
        foreach ($type1Options as $identifier => $label) {
            if (isset($assigned[$identifier])) {
                continue;
            }
            $groupName = trim((string) $label);
            if ($groupName === '') {
                $groupName = $identifier;
            }
            if ($groupName === $allGroupLabel) {
                $skippedAllGroup++;
                continue;
            }
            $group = new LoanGroup();
            $group->setName($groupName);
            $group->setType1Identifiers([$identifier]);
            $entityManager->persist($group);
            $created++;
        }

        if ($skippedAllGroup > 0) {
            $this->addFlash('warning', '一部の分類1は貸出グループ名の制限により作成できませんでした。');
        }
        if ($created === 0) {
            $this->addFlash('info', '作成対象がありませんでした。');
        } else {
            $entityManager->flush();
            $this->addFlash('success', sprintf('貸出グループを%d件作成しました。', $created));
        }

        return $this->redirectToRoute('app_settings_loan_group');
    }

    #[Route('/{id}/edit', name: 'app_settings_loan_group_edit', methods: ['GET'])]
    public function edit(LoanGroup $loanGroup, CodeRepository $codeRepository, TranslatorInterface $translator): Response
    {
        if ($this->isAllGroup($loanGroup, $translator)) {
            $this->addFlash('danger', 'この貸出グループは編集できません。');
            return $this->redirectToRoute('app_settings_loan_group');
        }
        $type1Options = $this->getType1Options($codeRepository);

        return $this->render('settings/loan_group/edit.html.twig', [
            'group' => $loanGroup,
            'type1Options' => $type1Options,
            'selectedType1' => $loanGroup->getType1Identifiers(),
        ]);
    }

    #[Route('/{id}/update', name: 'app_settings_loan_group_update', methods: ['POST'])]
    public function update(
        LoanGroup $loanGroup,
        Request $request,
        LoanGroupType1Repository $mappingRepository,
        CodeRepository $codeRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ): Response {
        if ($this->isAllGroup($loanGroup, $translator)) {
            $this->addFlash('danger', 'この貸出グループは編集できません。');
            return $this->redirectToRoute('app_settings_loan_group');
        }
        if (!$this->isCsrfTokenValid('loan_group_update_' . $loanGroup->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_group_edit', ['id' => $loanGroup->getId()]);
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('danger', '貸出グループ名は必須です。');
            return $this->redirectToRoute('app_settings_loan_group_edit', ['id' => $loanGroup->getId()]);
        }

        $type1Options = $this->getType1Options($codeRepository);
        $selected = $this->normalizeType1Identifiers($request->request->all('type1_identifiers'));
        if (!$this->isValidType1Selection($selected, $type1Options)) {
            $this->addFlash('danger', '分類1の選択が不正です。');
            return $this->redirectToRoute('app_settings_loan_group_edit', ['id' => $loanGroup->getId()]);
        }

        $conflicts = $mappingRepository->findConflicts($selected, $loanGroup->getId());
        if ($conflicts !== []) {
            foreach ($conflicts as $identifier => $groupName) {
                $this->addFlash('danger', sprintf('分類1 "%s" は既に貸出グループ "%s" に割り当て済みです。', $identifier, $groupName));
            }
            return $this->redirectToRoute('app_settings_loan_group_edit', ['id' => $loanGroup->getId()]);
        }

        $loanGroup->setName($name);
        $loanGroup->setType1Identifiers($selected);

        $entityManager->flush();

        $this->addFlash('success', '貸出グループを更新しました。');
        return $this->redirectToRoute('app_settings_loan_group');
    }

    #[Route('/{id}/delete', name: 'app_settings_loan_group_delete', methods: ['POST'])]
    public function delete(
        LoanGroup $loanGroup,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ): Response
    {
        if ($this->isAllGroup($loanGroup, $translator)) {
            $this->addFlash('danger', 'この貸出グループは削除できません。');
            return $this->redirectToRoute('app_settings_loan_group');
        }
        if (!$this->isCsrfTokenValid('loan_group_delete_' . $loanGroup->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_group');
        }

        $entityManager->remove($loanGroup);
        $entityManager->flush();

        $this->addFlash('success', '貸出グループを削除しました。');
        return $this->redirectToRoute('app_settings_loan_group');
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

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeType1Identifiers(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [];
        $normalized = [];
        foreach ($values as $value) {
            $val = trim((string) $value);
            if ($val !== '') {
                $normalized[] = $val;
            }
        }
        return array_values(array_unique($normalized));
    }

    /**
     * @param string[] $selected
     * @param array<string, string> $options
     */
    private function isValidType1Selection(array $selected, array $options): bool
    {
        if ($selected === []) {
            return true;
        }
        $available = array_keys($options);
        foreach ($selected as $value) {
            if (!in_array($value, $available, true)) {
                return false;
            }
        }
        return true;
    }

    private function isAllGroup(LoanGroup $loanGroup, TranslatorInterface $translator): bool
    {
        if (!$loanGroup->getType1Mappings()->isEmpty()) {
            return false;
        }
        return $loanGroup->getName() === $this->getAllGroupLabel($translator);
    }

    private function getAllGroupLabel(TranslatorInterface $translator): string
    {
        return $translator->trans('Model.LoanGroup.values.all_group_members_identifier');
    }
}
