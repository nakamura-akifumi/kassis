<?php

namespace App\Controller;

use App\Entity\ManifestationOrder;
use App\Entity\ManifestationOrderItem;
use App\Repository\ManifestationOrderItemRepository;
use App\Repository\ManifestationOrderRepository;
use App\Repository\ManifestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Registry;

#[Route('/acquisition')]
final class AcquisitionController extends AbstractController
{
    #[Route('', name: 'app_acquisition_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('acquisition/index.html.twig');
    }

    #[Route('/selection', name: 'app_acquisition_selection', methods: ['GET'])]
    public function selection(ManifestationRepository $manifestationRepository): Response
    {
        $manifestations = $manifestationRepository->findBy(['status1' => 'New'], ['id' => 'DESC']);
        return $this->render('acquisition/selection.html.twig', [
            'manifestations' => $manifestations,
        ]);
    }

    #[Route('/selection', name: 'app_acquisition_selection_submit', methods: ['POST'])]
    public function selectionSubmit(
        Request $request,
        ManifestationRepository $manifestationRepository,
        EntityManagerInterface $entityManager,
        Registry $workflowRegistry
    ): Response {
        $ids = $request->request->all('manifestation_ids');
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn($v) => $v > 0));

        if ($ids === []) {
            $this->addFlash('warning', '選択された資料がありません。');
            return $this->redirectToRoute('app_acquisition_selection');
        }

        $manifestations = $manifestationRepository->findBy(['id' => $ids]);
        $applied = 0;
        foreach ($manifestations as $manifestation) {
            $workflow = $workflowRegistry->get($manifestation, 'manifestation');
            if ($workflow->can($manifestation, 'selection')) {
                $workflow->apply($manifestation, 'selection');
                $applied++;
            }
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('選書を反映しました（%d件）。', $applied));
        return $this->redirectToRoute('app_acquisition_selection');
    }

    #[Route('/order', name: 'app_acquisition_order', methods: ['GET'])]
    public function order(ManifestationRepository $manifestationRepository): Response
    {
        $manifestations = $manifestationRepository->findBy(['status1' => 'Awaiting Order'], ['id' => 'DESC']);
        return $this->render('acquisition/order.html.twig', [
            'manifestations' => $manifestations,
        ]);
    }

    #[Route('/order', name: 'app_acquisition_order_submit', methods: ['POST'])]
    public function orderSubmit(
        Request $request,
        ManifestationRepository $manifestationRepository,
        EntityManagerInterface $entityManager,
        Registry $workflowRegistry
    ): Response {
        $ids = $request->request->all('manifestation_ids');
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn($v) => $v > 0));

        if ($ids === []) {
            $this->addFlash('warning', '選択された資料がありません。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $manifestations = $manifestationRepository->findBy(['id' => $ids]);
        if ($manifestations === []) {
            $this->addFlash('warning', '選択された資料が見つかりません。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $order = new ManifestationOrder();
        $order->setOrderNumber($this->generateOrderNumber());

        $applied = 0;
        foreach ($manifestations as $manifestation) {
            $workflow = $workflowRegistry->get($manifestation, 'manifestation');
            if (!$workflow->can($manifestation, 'order')) {
                continue;
            }

            $workflow->apply($manifestation, 'order');
            $item = new ManifestationOrderItem();
            $item->setManifestation($manifestation);
            $order->addItem($item);
            $applied++;
        }

        if ($applied === 0) {
            $this->addFlash('warning', '発注対象がありません。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $entityManager->persist($order);
        $entityManager->flush();

        $this->addFlash('success', sprintf('発注を登録しました（%d件）。', $applied));
        return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $order->getId()]);
    }

    #[Route('/receive', name: 'app_acquisition_receive', methods: ['GET'])]
    public function receive(
        Request $request,
        ManifestationOrderRepository $orderRepository
    ): Response {
        $orders = $orderRepository->findBy([], ['ordered_at' => 'DESC']);
        $orderId = $request->query->getInt('order_id');
        $selectedOrder = $orderId > 0 ? $orderRepository->find($orderId) : ($orders[0] ?? null);

        return $this->render('acquisition/receive.html.twig', [
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
        ]);
    }

    #[Route('/receive', name: 'app_acquisition_receive_submit', methods: ['POST'])]
    public function receiveSubmit(
        Request $request,
        ManifestationOrderRepository $orderRepository,
        ManifestationOrderItemRepository $itemRepository,
        ManifestationRepository $manifestationRepository,
        EntityManagerInterface $entityManager,
        Registry $workflowRegistry
    ): Response {
        $orderId = (int) $request->request->get('order_id');
        $identifier = trim((string) $request->request->get('manifestation_identifier'));

        if ($orderId <= 0 || $identifier === '') {
            $this->addFlash('danger', '発注と資料識別子を入力してください。');
            return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $orderId]);
        }

        $order = $orderRepository->find($orderId);
        if ($order === null) {
            $this->addFlash('danger', '発注が見つかりません。');
            return $this->redirectToRoute('app_acquisition_receive');
        }

        $manifestation = $manifestationRepository->findOneByIdentifierNormalized($identifier);
        if ($manifestation === null) {
            $this->addFlash('danger', '資料が見つかりません。');
            return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $orderId]);
        }

        $item = $itemRepository->findOneByOrderAndManifestation($order, $manifestation);
        if ($item === null) {
            $this->addFlash('danger', 'この発注に紐づかない資料です。');
            return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $orderId]);
        }

        if ($item->getReceivedAt() !== null) {
            $this->addFlash('info', 'この資料は既に受入済みです。');
            return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $orderId]);
        }

        $workflow = $workflowRegistry->get($manifestation, 'manifestation');
        if ($workflow->can($manifestation, 'receive')) {
            $workflow->apply($manifestation, 'receive');
        }

        $item->setReceivedAt(new \DateTime());

        $allReceived = true;
        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->getReceivedAt() === null) {
                $allReceived = false;
                break;
            }
        }
        if ($allReceived) {
            $order->setStatus(ManifestationOrder::STATUS_COMPLETED);
            $order->setCompletedAt(new \DateTime());
        }

        $entityManager->flush();
        $this->addFlash('success', '受入を反映しました。');
        return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $orderId]);
    }

    private function generateOrderNumber(): string
    {
        $date = (new \DateTime())->format('Ymd');
        $rand = substr(bin2hex(random_bytes(4)), 0, 6);
        return $date . '-' . $rand;
    }
}
