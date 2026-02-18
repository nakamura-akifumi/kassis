<?php

namespace App\Controller;

use App\Entity\LoanCondition;
use App\Entity\LoanGroup;
use App\Repository\LoanConditionRepository;
use App\Repository\LoanGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/settings/loan-condition')]
final class LoanConditionController extends AbstractController
{
    #[Route('', name: 'app_settings_loan_condition', methods: ['GET'])]
    public function index(
        LoanConditionRepository $loanConditionRepository,
        LoanGroupRepository $loanGroupRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator
    ): Response {
        $conditions = $loanConditionRepository->findBy([], ['id' => 'DESC']);
        $loanGroups = $loanGroupRepository->findBy([], ['name' => 'ASC']);
        $allGroupLabel = $this->getAllGroupLabel($translator);
        $loanGroups = $this->filterOutAllGroup($loanGroups, $allGroupLabel);
        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);

        return $this->render('settings/loan_condition/index.html.twig', [
            'conditions' => $conditions,
            'loanGroups' => $loanGroups,
            'memberGroupOptions' => $memberGroupOptions,
        ]);
    }


    #[Route('/create', name: 'app_settings_loan_condition_create', methods: ['POST'])]
    public function create(
        Request $request,
        LoanConditionRepository $loanConditionRepository,
        LoanGroupRepository $loanGroupRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('loan_condition_create', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $loanGroupSelection = (string) $request->request->get('loan_group_id');
        $loanGroupId = $this->parseLoanGroupSelection($loanGroupSelection);
        $memberGroup = trim((string) $request->request->get('member_group'));
        if ($loanGroupSelection === '' || $memberGroup === '') {
            $this->addFlash('danger', '貸出グループと利用者グループは必須です。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $loanGroup = null;
        if ($loanGroupSelection === 'all') {
            $loanGroup = $this->getOrCreateAllLoanGroup($loanGroupRepository, $translator, $entityManager);
        } else {
            if ($loanGroupId === null) {
                $this->addFlash('danger', '貸出グループが不正です。');
                return $this->redirectToRoute('app_settings_loan_condition');
            }
            $loanGroup = $loanGroupRepository->find($loanGroupId);
            if ($loanGroup === null) {
                $this->addFlash('danger', '貸出グループが見つかりません。');
                return $this->redirectToRoute('app_settings_loan_condition');
            }
        }

        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);
        if (!in_array($memberGroup, array_values($memberGroupOptions), true)) {
            $this->addFlash('danger', '利用者グループが不正です。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        if ($loanConditionRepository->findOneBy(['loanGroup' => $loanGroup, 'member_group' => $memberGroup]) !== null) {
            $this->addFlash('danger', '同じ組み合わせの貸出条件が既に存在します。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $loanLimit = $this->parseNonNegativeInt($request->request->get('loan_limit'));
        $loanPeriod = $this->parseNonNegativeInt($request->request->get('loan_period'));
        $renewLimit = $this->parseNonNegativeInt($request->request->get('renew_limit'));
        $reservationLimit = $this->parseNonNegativeInt($request->request->get('reservation_limit'));
        if ($loanLimit === null || $loanPeriod === null || $renewLimit === null || $reservationLimit === null) {
            $this->addFlash('danger', '数値項目は0以上の整数で入力してください。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $adjustDueOnClosedDay = $request->request->get('adjust_due_on_closed_day') === '1';

        $condition = new LoanCondition();
        $condition->setLoanGroup($loanGroup);
        $condition->setMemberGroup($memberGroup);
        $condition->setLoanLimit($loanLimit);
        $condition->setLoanPeriod($loanPeriod);
        $condition->setRenewLimit($renewLimit);
        $condition->setReservationLimit($reservationLimit);
        $condition->setAdjustDueOnClosedDay($adjustDueOnClosedDay);

        $entityManager->persist($condition);
        $entityManager->flush();

        $this->addFlash('success', '貸出条件を追加しました。');
        return $this->redirectToRoute('app_settings_loan_condition');
    }

    #[Route('/create-defaults', name: 'app_settings_loan_condition_create_defaults', methods: ['POST'])]
    public function createDefaults(
        Request $request,
        LoanConditionRepository $loanConditionRepository,
        LoanGroupRepository $loanGroupRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('loan_condition_create_defaults', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $defaults = $this->getDefaultValues($params);
        if ($defaults === null) {
            $this->addFlash('danger', 'デフォルト値の設定が不正です。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $loanGroups = $loanGroupRepository->findBy([], ['name' => 'ASC']);
        if ($loanGroups === []) {
            $this->addFlash('warning', '貸出グループが未登録のため作成できません。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);
        $memberGroups = array_values($memberGroupOptions);
        if ($memberGroups === []) {
            $this->addFlash('warning', '利用者グループが未設定のため作成できません。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $existing = $loanConditionRepository->findAll();
        $existingKeys = [];
        foreach ($existing as $condition) {
            $groupId = $condition->getLoanGroup()?->getId();
            if ($groupId === null) {
                continue;
            }
            $existingKeys[$groupId . ':' . $condition->getMemberGroup()] = true;
        }

        $created = 0;
        foreach ($loanGroups as $loanGroup) {
            foreach ($memberGroups as $memberGroup) {
                $key = $loanGroup->getId() . ':' . $memberGroup;
                if (isset($existingKeys[$key])) {
                    continue;
                }
                $condition = new LoanCondition();
                $condition->setLoanGroup($loanGroup);
                $condition->setMemberGroup($memberGroup);
                $condition->setLoanLimit($defaults['loan_limit']);
                $condition->setLoanPeriod($defaults['loan_period']);
                $condition->setRenewLimit($defaults['renew_limit']);
                $condition->setReservationLimit($defaults['reservation_limit']);
                $condition->setAdjustDueOnClosedDay($defaults['adjust_due_on_closed_day']);
                $entityManager->persist($condition);
                $created++;
            }
        }

        if ($created === 0) {
            $this->addFlash('info', '作成対象がありませんでした。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('貸出条件を%d件作成しました。', $created));
        return $this->redirectToRoute('app_settings_loan_condition');
    }

    #[Route('/{id}/edit', name: 'app_settings_loan_condition_edit', methods: ['GET'])]
    public function edit(
        LoanCondition $loanCondition,
        LoanGroupRepository $loanGroupRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator
    ): Response {
        $loanGroups = $loanGroupRepository->findBy([], ['name' => 'ASC']);
        $allGroupLabel = $this->getAllGroupLabel($translator);
        $loanGroups = $this->filterOutAllGroup($loanGroups, $allGroupLabel);
        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);

        return $this->render('settings/loan_condition/edit.html.twig', [
            'condition' => $loanCondition,
            'loanGroups' => $loanGroups,
            'memberGroupOptions' => $memberGroupOptions,
        ]);
    }

    #[Route('/{id}/update', name: 'app_settings_loan_condition_update', methods: ['POST'])]
    public function update(
        LoanCondition $loanCondition,
        Request $request,
        LoanConditionRepository $loanConditionRepository,
        LoanGroupRepository $loanGroupRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('loan_condition_update_' . $loanCondition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
        }

        $loanGroupSelection = (string) $request->request->get('loan_group_id');
        $loanGroupId = $this->parseLoanGroupSelection($loanGroupSelection);
        $memberGroup = trim((string) $request->request->get('member_group'));
        if ($loanGroupSelection === '' || $memberGroup === '') {
            $this->addFlash('danger', '貸出グループと利用者グループは必須です。');
            return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
        }

        $loanGroup = null;
        if ($loanGroupSelection === 'all') {
            $loanGroup = $this->getOrCreateAllLoanGroup($loanGroupRepository, $translator, $entityManager);
        } else {
            if ($loanGroupId === null) {
                $this->addFlash('danger', '貸出グループが不正です。');
                return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
            }
            $loanGroup = $loanGroupRepository->find($loanGroupId);
            if ($loanGroup === null) {
                $this->addFlash('danger', '貸出グループが見つかりません。');
                return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
            }
        }

        $memberGroupOptions = $this->buildMemberGroupChoices($params, $translator);
        if (!in_array($memberGroup, array_values($memberGroupOptions), true)) {
            $this->addFlash('danger', '利用者グループが不正です。');
            return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
        }

        $existing = $loanConditionRepository->findOneBy(['loanGroup' => $loanGroup, 'member_group' => $memberGroup]);
        if ($existing !== null && $existing->getId() !== $loanCondition->getId()) {
            $this->addFlash('danger', '同じ組み合わせの貸出条件が既に存在します。');
            return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
        }

        $loanLimit = $this->parseNonNegativeInt($request->request->get('loan_limit'));
        $loanPeriod = $this->parseNonNegativeInt($request->request->get('loan_period'));
        $renewLimit = $this->parseNonNegativeInt($request->request->get('renew_limit'));
        $reservationLimit = $this->parseNonNegativeInt($request->request->get('reservation_limit'));
        if ($loanLimit === null || $loanPeriod === null || $renewLimit === null || $reservationLimit === null) {
            $this->addFlash('danger', '数値項目は0以上の整数で入力してください。');
            return $this->redirectToRoute('app_settings_loan_condition_edit', ['id' => $loanCondition->getId()]);
        }

        $adjustDueOnClosedDay = $request->request->get('adjust_due_on_closed_day') === '1';

        $loanCondition->setLoanGroup($loanGroup);
        $loanCondition->setMemberGroup($memberGroup);
        $loanCondition->setLoanLimit($loanLimit);
        $loanCondition->setLoanPeriod($loanPeriod);
        $loanCondition->setRenewLimit($renewLimit);
        $loanCondition->setReservationLimit($reservationLimit);
        $loanCondition->setAdjustDueOnClosedDay($adjustDueOnClosedDay);

        $entityManager->flush();

        $this->addFlash('success', '貸出条件を更新しました。');
        return $this->redirectToRoute('app_settings_loan_condition');
    }

    #[Route('/{id}/delete', name: 'app_settings_loan_condition_delete', methods: ['POST'])]
    public function delete(LoanCondition $loanCondition, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('loan_condition_delete_' . $loanCondition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_loan_condition');
        }

        $entityManager->remove($loanCondition);
        $entityManager->flush();

        $this->addFlash('success', '貸出条件を削除しました。');
        return $this->redirectToRoute('app_settings_loan_condition');
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

    private function parseNonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = trim((string) $value);
        if ($str === '' || !preg_match('/^\d+$/', $str)) {
            return null;
        }
        return (int) $str;
    }

    private function parseIntOrNull(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }
        return (int) $value;
    }

    private function parseLoanGroupSelection(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'all') {
            return null;
        }
        return $this->parseIntOrNull($trimmed);
    }

    private function getAllGroupLabel(TranslatorInterface $translator): string
    {
        return $translator->trans('Model.LoanGroup.values.all_group_members_identifier');
    }

    /**
     * @param LoanGroup[] $loanGroups
     * @return LoanGroup[]
     */
    private function filterOutAllGroup(array $loanGroups, string $allGroupLabel): array
    {
        return array_values(array_filter(
            $loanGroups,
            static fn(LoanGroup $group) => $group->getName() !== $allGroupLabel
        ));
    }

    private function getOrCreateAllLoanGroup(
        LoanGroupRepository $loanGroupRepository,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): LoanGroup {
        $allGroupLabel = $this->getAllGroupLabel($translator);
        $existing = $loanGroupRepository->findOneBy(['name' => $allGroupLabel]);
        if ($existing instanceof LoanGroup) {
            return $existing;
        }
        $group = new LoanGroup();
        $group->setName($allGroupLabel);
        $entityManager->persist($group);
        return $group;
    }

    /**
     * @return array{loan_limit:int, loan_period:int, renew_limit:int, reservation_limit:int, adjust_due_on_closed_day:bool}|null
     */
    private function getDefaultValues(ParameterBagInterface $params): ?array
    {
        if (!$params->has('app.loan_condition.defaults')) {
            return null;
        }
        $raw = (array) $params->get('app.loan_condition.defaults');
        $loanLimit = $this->parseNonNegativeInt($raw['loan_limit'] ?? null);
        $loanPeriod = $this->parseNonNegativeInt($raw['loan_period'] ?? null);
        $renewLimit = $this->parseNonNegativeInt($raw['renew_limit'] ?? null);
        $reservationLimit = $this->parseNonNegativeInt($raw['reservation_limit'] ?? null);
        if ($loanLimit === null || $loanPeriod === null || $renewLimit === null || $reservationLimit === null) {
            return null;
        }
        $adjust = $raw['adjust_due_on_closed_day'] ?? false;
        $adjust = filter_var($adjust, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($adjust === null) {
            $adjust = false;
        }

        return [
            'loan_limit' => $loanLimit,
            'loan_period' => $loanPeriod,
            'renew_limit' => $renewLimit,
            'reservation_limit' => $reservationLimit,
            'adjust_due_on_closed_day' => $adjust,
        ];
    }
}
