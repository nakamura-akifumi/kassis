<?php

namespace App\Service;

use App\Entity\Checkout;
use App\Entity\LoanCondition;
use App\Entity\LoanGroup;
use App\Entity\LoanGroupType1;
use App\Entity\Manifestation;
use App\Entity\Reservation;
use App\Repository\CheckoutRepository;
use App\Repository\ManifestationRepository;
use App\Repository\MemberRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Contracts\Translation\TranslatorInterface;

class CirculationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManifestationRepository $manifestationRepository,
        private MemberRepository $memberRepository,
        private ReservationRepository $reservationRepository,
        private CheckoutRepository $checkoutRepository,
        private CalendarService $calendarService,
        private Registry $workflowRegistry,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private ParameterBagInterface $params,
    ) {
    }

    public function reserve(string $memberIdentifier, string $manifestationIdentifier, ?int $expiryDate = null): Reservation
    {
        $member = $this->memberRepository->findOneBy(['identifier' => $memberIdentifier]);
        if ($member === null) {
            throw new \InvalidArgumentException('Member not found.');
        }

        $manifestation = $this->manifestationRepository->findOneByIdentifierNormalized($manifestationIdentifier);
        if ($manifestation === null) {
            throw new \InvalidArgumentException('Manifestation not found.');
        }

        $reservation = new Reservation();
        $reservation->setMember($member);
        $reservation->setManifestation($manifestation);
        $reservation->setReservedAt(time());
        $reservation->setExpiryDate($expiryDate);
        $reservation->setStatus(Reservation::STATUS_WAITING);

        $this->entityManager->persist($reservation);

        $workflow = $this->getManifestationWorkflow($manifestation);
        if ($workflow->can($manifestation, 'reserve')) {
            $workflow->apply($manifestation, 'reserve');
        }

        $this->entityManager->flush();

        return $reservation;
    }

    /**
     * @return Checkout[]
     */
    public function checkout(string $memberIdentifier, array $manifestationIdentifiers): array
    {
        $this->logger->debug('checkout start');

        // a. 対象者(member)のmemberテーブルを検索
        $member = $this->memberRepository->findOneBy(['identifier' => $memberIdentifier]);
        if ($member === null) {
            throw new \InvalidArgumentException('Member not found.');
        }

        // b. memberの状態(status)が無効(inactive)の場合 → 対象の利用者の状態が無効なので貸出が出来ません。
        if (!$member->isActive()) {
            throw new \InvalidArgumentException('Member is not active.');
        }

        $results = [];
        $now = new \DateTime();

        foreach ($manifestationIdentifiers as $manifestationIdentifier) {
            // c. 対象のmanifestationのチェック
            $manifestation = $this->manifestationRepository->findOneByIdentifierNormalized($manifestationIdentifier);
            if ($manifestation === null) {
                throw new \InvalidArgumentException('Manifestation not found: ' . $manifestationIdentifier);
            }
            // d. ($manifestation)の利用制限を確認
            if ($manifestation->isRestricted()) {
                throw new \InvalidArgumentException('Manifestation is restricted.');
            }

            // e. ($member) の貸出状況を確認する
            $checkoutCountsByLoanGroup = $this->checkoutRepository->countActiveByMemberGroupedByLoanGroup($member);

            // 2. Manifestationの分類１に対する貸出グループを取得
            $loanGroup = null;
            $loanGroupType1Identifiers = null;
            $type1Identifier = $manifestation->getType1();
            if (is_string($type1Identifier) && trim($type1Identifier) !== '') {
                $loanGroupType1 = $this->entityManager->getRepository(LoanGroupType1::class)->findOneBy([
                    'type1_identifier' => $type1Identifier,
                ]);
                $loanGroup = $loanGroupType1?->getLoanGroup();
/*
                if ($loanGroup !== null) {
                    $loanGroupType1Identifiers = $this->entityManager->getRepository(LoanGroupType1::class)->findBy([
                        'loan_group_id' => $loanGroup?->getId(),
                    ]);
                }
*/
            }


            // 3. loan_group テーブルに messages.ja.yaml の Model.LoanGroup.values.all_group_members_identifier に設定されている文字列と同じnameのレコードがあるかチェックして、ある場合は取得
            $allGroupMembersIdentifier = $this->translator->trans('Model.LoanGroup.values.all_group_members_identifier', [], 'messages', 'ja');
            $allGroupMembersLoanGroup = $this->entityManager->getRepository(LoanGroup::class)->findOneBy([
                'name' => $allGroupMembersIdentifier,
            ]);

            // 4-1:
            $loanCondition = $this->entityManager->getRepository(LoanCondition::class)->findOneBy([
                'loanGroup' => $loanGroup,
                'member_group' => $member->getGroup1(),
            ]);

            // 4-2:
            $loanAllGroupCondition = $this->entityManager->getRepository(LoanCondition::class)->findOneBy([
                'loanGroup' => $allGroupMembersLoanGroup,
                'member_group' => $member->getGroup1(),
            ]);

            if ($loanCondition === null && $loanAllGroupCondition === null) {
                //throw new \RuntimeException('Loan condition not found for loan group and member group');

                if (!$this->params->has('app.checkout.due_days') &&
                    !$this->params->has('app.checkout.loan_limit') &&
                    !$this->params->has('app.checkout.renew_limit') &&
                    !$this->params->has('app.checkout.reservation_limit') &&
                    !$this->params->has('app.checkout.adjust_due_on_closed_day')
                ) {
                    throw new \RuntimeException('Loan condition not found for loan group and member group and config/parameter');
                }

                $loanCondition = new LoanCondition();
                $loanCondition->setLoanPeriod($this->params->get('app.checkout.due_days'));
                $loanCondition->setLoanLimit($this->params->get('app.checkout.loan_limit'));
                $loanCondition->setRenewLimit($this->params->get('app.checkout.renew_limit'));
                $loanCondition->setReservationLimit($this->params->get('app.checkout.reservation_limit'));
                $loanCondition->setAdjustDueOnClosedDay($this->params->get('app.checkout.adjust_due_on_closed_day'));

            }

            // ５−１：貸出冊数を超過していないかを確認（全体）
            $allCheckoutCount = $this->checkoutRepository->countActiveByMember($member);
            if ($loanAllGroupCondition !== null && $allCheckoutCount + 1 > $loanAllGroupCondition->getLoanLimit()) {
                throw new \RuntimeException('Loan condition max count exceeded');
            }

            // ５−２：対象のmanifestationの貸出条件で貸出冊数を超過していないかを確認
            if ($loanCondition !== null) {
                $count = 0;
                foreach ($checkoutCountsByLoanGroup as $co) {
                    if ($co['loan_group_id'] === $loanGroup?->getId()) {
                        $count += (int) $co['cnt'];
                    }
                }
                if ($count + 1 > $loanCondition->getLoanLimit()) {
                    throw new \RuntimeException('Loan condition max count exceeded');
                }
            }

            // 貸出日付算出
            $dueDate = null;
            if ($loanCondition !== null) {
                $this->logger->debug('@1 loan due date calculated:'.var_export($loanCondition, true));
                $dueDate = $this->calculateDueDate($now, $loanCondition->getLoanPeriod(), $loanCondition->isAdjustDueOnClosedDay());
            } else {
                $this->logger->debug('@2 loan due date calculated:'.var_export($loanAllGroupCondition, true));
                $dueDate = $this->calculateDueDate($now, $loanAllGroupCondition->getLoanPeriod(), $loanAllGroupCondition->isAdjustDueOnClosedDay());
            }

            if ($dueDate === null) {
                throw new \RuntimeException('Loan due date not calculated');
            }

            $this->logger->debug('Loan due date calculated: ' . $dueDate->format('Y-m-d'));

            $checkout = new Checkout();
            $checkout->setMember($member);
            $checkout->setManifestation($manifestation);
            $checkout->setCheckedOutAt($now);
            $checkout->setStatus(Checkout::STATUS_CHECKED_OUT);
            $checkout->setDueDate($dueDate);

            $reservation = $this->reservationRepository->findWaitingByManifestationAndMember($manifestation, $member);
            if ($reservation !== null) {
                $reservation->setStatus(Reservation::STATUS_COMPLETED);
            }

            $workflow = $this->getManifestationWorkflow($manifestation);
            if ($workflow->can($manifestation, 'check_out')) {
                $workflow->apply($manifestation, 'check_out');
            }

            $this->entityManager->persist($checkout);
            $results[] = $checkout;
        }

        $this->entityManager->flush();

        return $results;
    }

    public function checkIn(string $manifestationIdentifier): ?Checkout
    {
        $manifestation = $this->manifestationRepository->findOneByIdentifierNormalized($manifestationIdentifier);
        if ($manifestation === null) {
            throw new \InvalidArgumentException('Manifestation not found.');
        }

        $checkout = $this->checkoutRepository->findActiveByManifestation($manifestation);
        if ($checkout === null) {
            $checkout = $this->checkoutRepository->findLatestByManifestation($manifestation);
        }
        $now = new \DateTime();

        if ($checkout !== null && $checkout->getCheckedInAt() === null) {
            $checkout->setCheckedInAt($now);
            $checkout->setStatus(Checkout::STATUS_RETURNED);
        }

        $workflow = $this->getManifestationWorkflow($manifestation);
        if ($workflow->can($manifestation, 'check_in')) {
            $workflow->apply($manifestation, 'check_in');
        }

        $reservation = $this->reservationRepository->findOldestWaitingByManifestation($manifestation);
        if ($reservation !== null) {
            $reservation->setStatus(Reservation::STATUS_AVAILABLE);
            if ($workflow->can($manifestation, 'reserve')) {
                $workflow->apply($manifestation, 'reserve');
            }
        }

        $this->entityManager->flush();

        return $checkout;
    }

    private function getManifestationWorkflow(Manifestation $manifestation): WorkflowInterface
    {
        return $this->workflowRegistry->get($manifestation, 'manifestation');
    }

    private function calculateDueDate(\DateTimeInterface $base, int $days, bool $isAdjustDueOnClosedDay): ?\DateTimeInterface
    {
        if ($days <= 0 || $days === 9999) {
            return null;
        }

        $date = ($base instanceof \DateTimeImmutable) ? $base : \DateTimeImmutable::createFromInterface($base);
        $dueDate = $date->modify('+' . $days . ' days');

        return $this->calendarService->adjustToNextOpenDate($dueDate, $isAdjustDueOnClosedDay);
    }
}
