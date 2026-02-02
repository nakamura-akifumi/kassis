<?php

namespace App\Controller;

use App\Entity\Member;
use App\Form\MemberType;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/member')]
class MemberController extends AbstractController
{
    #[Route('/', name: 'app_member_index', methods: ['GET'])]
    public function index(Request $request, MemberRepository $memberRepository, \Symfony\Contracts\Translation\TranslatorInterface $translator): Response
    {
        $searchTerm = $request->query->get('q');
        $members = $memberRepository->findBySearchTerm($searchTerm);
        $gridData = array_map(static function (Member $member) use ($translator): array {
            $group1Value = $member->getGroup1();
            $group1Key = 'Model.Member.values.Group1.' . $group1Value;
            $group1Label = $translator->trans($group1Key);
            if ($group1Label === $group1Key) {
                $group1Label = $group1Value;
            }
            $roleValue = $member->getRole();
            $roleLabel = null;
            if ($roleValue !== null && trim($roleValue) !== '') {
                $roleKey = 'Model.Member.values.Role.' . $roleValue;
                $roleLabel = $translator->trans($roleKey);
                if ($roleLabel === $roleKey) {
                    $roleLabel = $roleValue;
                }
            }
            return [
                'id' => $member->getId(),
                'identifier' => $member->getIdentifier(),
                'fullName' => $member->getFullName(),
                'group1Label' => $group1Label,
                'roleLabel' => $roleLabel,
                'status' => $member->getStatusLabel(),
                'expiryDate' => $member->getExpiryDate()?->format('Y-m-d'),
                'updatedAt' => $member->getUpdatedAt()?->format('Y-m-d H:i'),
                'note' => $member->getNote(),
            ];
        }, $members);

        return $this->render('member/index.html.twig', [
            'members' => $members,
            'gridData' => $gridData,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/new', name: 'app_member_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ParameterBagInterface $params, \Symfony\Contracts\Translation\TranslatorInterface $translator): Response
    {
        $member = new Member();
        if ($params->has('app.member.expiry_days')) {
            $expiryDays = $params->get('app.member.expiry_days');
            $expiryDays = is_numeric($expiryDays) ? (int) $expiryDays : null;
            if ($member->getExpiryDate() === null && $expiryDays !== null && $expiryDays > 0 && $expiryDays !== 9999) {
                $member->setExpiryDate((new \DateTime())->modify('+' . $expiryDays . ' days'));
            }
        }
        if ($member->getStatus() === null) {
            $member->setStatus(\App\Entity\Member::STATUS_ACTIVE);
        }
        if ($member->getRole() === null) {
            $member->setRole('member');
        }
        $choices = $this->buildGroup1Choices($params, $translator);
        $roleChoices = $this->buildRoleChoices($params, $translator);
        $form = $this->createForm(MemberType::class, $member, [
            'group1_choices' => $choices,
            'role_choices' => $roleChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($member);
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/new.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Member $member, \App\Repository\ReservationRepository $reservationRepository, \App\Repository\CheckoutRepository $checkoutRepository): Response
    {
        $reservationCount = $reservationRepository->countActiveByMember($member);
        $checkoutCount = $checkoutRepository->countActiveByMember($member);
        $rawStatus = trim((string) $member->getStatus());
        $statusLabel = (string) $member->getStatusLabel();
        $activeLabel = \App\Entity\Member::STATUS_LABELS[\App\Entity\Member::STATUS_ACTIVE];
        $isActive = false;
        if ($rawStatus !== '') {
            $isActive = strcasecmp($rawStatus, \App\Entity\Member::STATUS_ACTIVE) === 0
                || strcasecmp($rawStatus, $activeLabel) === 0;
        }
        if (!$isActive && $statusLabel !== '') {
            $isActive = strcasecmp($statusLabel, $activeLabel) === 0;
        }

        return $this->render('member/show.html.twig', [
            'member' => $member,
            'reservationCount' => $reservationCount,
            'checkoutCount' => $checkoutCount,
            'isActive' => $isActive,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Member $member, EntityManagerInterface $entityManager, ParameterBagInterface $params, \Symfony\Contracts\Translation\TranslatorInterface $translator): Response
    {
        $choices = $this->buildGroup1Choices($params, $translator);
        $roleChoices = $this->buildRoleChoices($params, $translator);
        $form = $this->createForm(MemberType::class, $member, [
            'group1_choices' => $choices,
            'role_choices' => $roleChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $member->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($member);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/check', name: 'app_member_check', methods: ['POST'])]
    public function check(Request $request, MemberRepository $memberRepository, \App\Repository\ReservationRepository $reservationRepository, \App\Repository\CheckoutRepository $checkoutRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $identifier = $data['identifier'] ?? null;

        if (!$identifier) {
            return new JsonResponse(['exists' => false], 400);
        }

        $member = $memberRepository->findOneBy(['identifier' => $identifier]);

        $reservationCount = 0;
        $checkoutCount = 0;
        $isActive = false;
        if ($member !== null) {
            $reservationCount = $reservationRepository->countActiveByMember($member);
            $checkoutCount = $checkoutRepository->countActiveByMember($member);

            $rawStatus = trim((string) $member->getStatus());
            $statusLabel = (string) $member->getStatusLabel();
            $activeLabel = \App\Entity\Member::STATUS_LABELS[\App\Entity\Member::STATUS_ACTIVE];
            if ($rawStatus !== '') {
                $isActive = strcasecmp($rawStatus, \App\Entity\Member::STATUS_ACTIVE) === 0
                    || strcasecmp($rawStatus, $activeLabel) === 0;
            }
            if (!$isActive && $statusLabel !== '') {
                $isActive = strcasecmp($statusLabel, $activeLabel) === 0;
            }
        }

        return new JsonResponse([
            'exists' => $member !== null,
            'fullName' => $member?->getFullName(),
            'checkoutCount' => $checkoutCount,
            'reservationCount' => $reservationCount,
            'note' => $member?->getNote(),
            'isActive' => $isActive,
        ]);
    }

    private function buildGroup1Choices(ParameterBagInterface $params, \Symfony\Contracts\Translation\TranslatorInterface $translator): array
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

    private function buildRoleChoices(ParameterBagInterface $params, \Symfony\Contracts\Translation\TranslatorInterface $translator): array
    {
        $roleChoices = $params->has('app.member.role') ? (array) $params->get('app.member.role') : [];
        $roleChoices = array_values(array_filter($roleChoices, static fn($value) => trim((string) $value) !== ''));
        $choices = [];
        foreach ($roleChoices as $value) {
            $key = 'Model.Member.values.Role.' . $value;
            $label = $translator->trans($key);
            if ($label === $key) {
                $label = $value;
            }
            $choices[$label] = $value;
        }
        return $choices;
    }
}
