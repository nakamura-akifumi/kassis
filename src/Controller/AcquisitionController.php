<?php

namespace App\Controller;

use App\Entity\ManifestationOrder;
use App\Entity\ManifestationOrderItem;
use App\Repository\ManifestationOrderItemRepository;
use App\Repository\ManifestationOrderRepository;
use App\Repository\ManifestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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

    #[Route('/orders', name: 'app_acquisition_order_manage', methods: ['GET'])]
    public function orderManage(ManifestationOrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy(
            ['status' => [ManifestationOrder::STATUS_IN_PROGRESS, ManifestationOrder::STATUS_ORDERED, ManifestationOrder::STATUS_COMPLETED]],
            ['ordered_at' => 'DESC']
        );

        return $this->render('acquisition/order_manage.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/orders/{id}/edit', name: 'app_acquisition_order_edit', methods: ['GET', 'POST'])]
    public function orderEdit(
        Request $request,
        ManifestationOrder $order,
        ManifestationOrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        if ($order->getStatus() === ManifestationOrder::STATUS_DELETED) {
            $this->addFlash('danger', '削除済みの発注書は編集できません。');
            return $this->redirectToRoute('app_acquisition_order_manage');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('order_edit_' . $order->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', '不正なリクエストです。');
                return $this->redirectToRoute('app_acquisition_order_edit', ['id' => $order->getId()]);
            }

            $orderNumber = trim((string) $request->request->get('order_number'));
            $vendor = trim((string) $request->request->get('vendor'));
            $orderAmountRaw = trim((string) $request->request->get('order_amount'));
            $deliveryDueRaw = trim((string) $request->request->get('delivery_due_date'));
            $estimateNumber = trim((string) $request->request->get('estimate_number'));
            $vendorContact = trim((string) $request->request->get('vendor_contact'));
            $orderContact = trim((string) $request->request->get('order_contact'));
            $externalReference = trim((string) $request->request->get('external_reference'));
            $memo = trim((string) $request->request->get('memo'));

            if ($orderNumber === '') {
                $this->addFlash('danger', '発注番号は必須です。');
                return $this->redirectToRoute('app_acquisition_order_edit', ['id' => $order->getId()]);
            }

            $existing = $orderRepository->findOneBy(['order_number' => $orderNumber]);
            if ($existing !== null && $existing->getId() !== $order->getId()) {
                $this->addFlash('danger', '同じ発注番号が既に存在します。');
                return $this->redirectToRoute('app_acquisition_order_edit', ['id' => $order->getId()]);
            }

            $order->setOrderNumber($orderNumber);
            $order->setVendor($vendor !== '' ? $vendor : null);
            $order->setEstimateNumber($estimateNumber !== '' ? $estimateNumber : null);
            $order->setVendorContact($vendorContact !== '' ? $vendorContact : null);
            $order->setOrderContact($orderContact !== '' ? $orderContact : null);
            $order->setExternalReference($externalReference !== '' ? $externalReference : null);
            $order->setMemo($memo !== '' ? $memo : null);

            $order->setOrderAmount($orderAmountRaw !== '' ? (int) $orderAmountRaw : null);
            $order->setDeliveryDueDate($deliveryDueRaw !== '' ? new \DateTime($deliveryDueRaw) : null);

            $entityManager->flush();
            $this->addFlash('success', '発注書を更新しました。');
            return $this->redirectToRoute('app_acquisition_order_manage');
        }

        return $this->render('acquisition/order_edit.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/orders/{id}/delete', name: 'app_acquisition_order_delete', methods: ['POST'])]
    public function orderDelete(Request $request, ManifestationOrder $order, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('order_delete_' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_acquisition_order_manage');
        }

        $order->setStatus(ManifestationOrder::STATUS_DELETED);
        $entityManager->flush();

        $this->addFlash('success', '発注書を削除しました。');
        return $this->redirectToRoute('app_acquisition_order_manage');
    }

    #[Route('/order/new', name: 'app_acquisition_order_new', methods: ['GET'])]
    public function orderNew(): Response
    {
        return $this->render('acquisition/order_new.html.twig');
    }

    #[Route('/order/new', name: 'app_acquisition_order_new_submit', methods: ['POST'])]
    public function orderNewSubmit(Request $request, ManifestationOrderRepository $orderRepository, EntityManagerInterface $entityManager): Response
    {
        $orderNumber = trim((string) $request->request->get('order_number'));
        $vendor = trim((string) $request->request->get('vendor'));
        $orderAmountRaw = trim((string) $request->request->get('order_amount'));
        $deliveryDueRaw = trim((string) $request->request->get('delivery_due_date'));
        $estimateNumber = trim((string) $request->request->get('estimate_number'));
        $vendorContact = trim((string) $request->request->get('vendor_contact'));
        $orderContact = trim((string) $request->request->get('order_contact'));
        $externalReference = trim((string) $request->request->get('external_reference'));
        $memo = trim((string) $request->request->get('memo'));

        if ($orderNumber === '') {
            $this->addFlash('danger', '発注番号は必須です。');
            return $this->redirectToRoute('app_acquisition_order_new');
        }

        if ($orderRepository->findOneBy(['order_number' => $orderNumber]) !== null) {
            $this->addFlash('danger', '同じ発注番号が既に存在します。');
            return $this->redirectToRoute('app_acquisition_order_new');
        }

        $order = new ManifestationOrder();
        $order->setOrderNumber($orderNumber);
        $order->setStatus(ManifestationOrder::STATUS_IN_PROGRESS);
        $order->setVendor($vendor !== '' ? $vendor : null);
        $order->setEstimateNumber($estimateNumber !== '' ? $estimateNumber : null);
        $order->setVendorContact($vendorContact !== '' ? $vendorContact : null);
        $order->setOrderContact($orderContact !== '' ? $orderContact : null);
        $order->setExternalReference($externalReference !== '' ? $externalReference : null);
        $order->setMemo($memo !== '' ? $memo : null);

        if ($orderAmountRaw !== '') {
            $order->setOrderAmount((int) $orderAmountRaw);
        }
        if ($deliveryDueRaw !== '') {
            $order->setDeliveryDueDate(new \DateTime($deliveryDueRaw));
        }

        $entityManager->persist($order);
        $entityManager->flush();

        $this->addFlash('success', '発注書を作成しました。');
        return $this->redirectToRoute('app_acquisition_selection', ['order_id' => $order->getId()]);
    }

    #[Route('/selection', name: 'app_acquisition_selection', methods: ['GET'])]
    public function selection(
        Request $request,
        ManifestationOrderRepository $orderRepository,
        ManifestationRepository $manifestationRepository
    ): Response {
        $orders = $orderRepository->findBy(['status' => ManifestationOrder::STATUS_IN_PROGRESS], ['ordered_at' => 'DESC']);
        $orderId = $request->query->getInt('order_id');
        $selectedOrder = $orderId > 0 ? $orderRepository->find($orderId) : ($orders[0] ?? null);
        $manifestations = $manifestationRepository->findNewWithoutOrder();

        return $this->render('acquisition/selection.html.twig', [
            'manifestations' => $manifestations,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
        ]);
    }

    #[Route('/selection', name: 'app_acquisition_selection_submit', methods: ['POST'])]
    public function selectionSubmit(
        Request $request,
        ManifestationRepository $manifestationRepository,
        ManifestationOrderRepository $orderRepository,
        ManifestationOrderItemRepository $itemRepository,
        EntityManagerInterface $entityManager,
        Registry $workflowRegistry
    ): Response {
        $orderId = (int) $request->request->get('order_id');
        $ids = $request->request->all('manifestation_ids');
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn($v) => $v > 0));

        if ($orderId <= 0) {
            $this->addFlash('danger', '発注書を選択してください。');
            return $this->redirectToRoute('app_acquisition_selection');
        }

        $order = $orderRepository->find($orderId);
        if ($order === null || $order->getStatus() !== ManifestationOrder::STATUS_IN_PROGRESS) {
            $this->addFlash('danger', '発注書が見つかりません。');
            return $this->redirectToRoute('app_acquisition_selection');
        }

        if ($ids === []) {
            $this->addFlash('warning', '選択された資料がありません。');
            return $this->redirectToRoute('app_acquisition_selection', ['order_id' => $orderId]);
        }

        $manifestations = $manifestationRepository->findBy(['id' => $ids]);
        $applied = 0;
        foreach ($manifestations as $manifestation) {
            $workflow = $workflowRegistry->get($manifestation, 'manifestation');
            if ($workflow->can($manifestation, 'selection')) {
                $workflow->apply($manifestation, 'selection');
            }
            $existing = $itemRepository->findOneByOrderAndManifestation($order, $manifestation);
            if ($existing === null) {
                $item = new ManifestationOrderItem();
                $item->setManifestation($manifestation);
                $order->addItem($item);
                $applied++;
            }
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('選書を反映しました（%d件）。', $applied));
        return $this->redirectToRoute('app_acquisition_selection', ['order_id' => $orderId]);
    }

    #[Route('/order', name: 'app_acquisition_order', methods: ['GET'])]
    public function order(
        Request $request,
        ManifestationOrderRepository $orderRepository,
        ManifestationOrderItemRepository $itemRepository
    ): Response {
        $orders = $orderRepository->findBy(['status' => ManifestationOrder::STATUS_IN_PROGRESS], ['ordered_at' => 'DESC']);
        $orderId = $request->query->getInt('order_id');
        $selectedOrder = $orderId > 0 ? $orderRepository->find($orderId) : ($orders[0] ?? null);
        $items = $selectedOrder ? $itemRepository->findAwaitingByOrder($selectedOrder) : [];
        $manifestations = array_map(static fn(ManifestationOrderItem $item) => $item->getManifestation(), $items);

        return $this->render('acquisition/order.html.twig', [
            'manifestations' => $manifestations,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
        ]);
    }

    #[Route('/order', name: 'app_acquisition_order_submit', methods: ['POST'])]
    public function orderSubmit(
        Request $request,
        ManifestationOrderRepository $orderRepository,
        ManifestationOrderItemRepository $itemRepository,
        EntityManagerInterface $entityManager,
        Registry $workflowRegistry
    ): Response {
        $orderId = (int) $request->request->get('order_id');
        $ids = $request->request->all('manifestation_ids');
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn($v) => $v > 0));
        if ($orderId <= 0) {
            $this->addFlash('danger', '発注書を選択してください。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        if ($ids === []) {
            $this->addFlash('warning', '選択された資料がありません。');
            return $this->redirectToRoute('app_acquisition_order', ['order_id' => $orderId]);
        }

        $order = $orderRepository->find($orderId);
        if ($order === null || $order->getStatus() !== ManifestationOrder::STATUS_IN_PROGRESS) {
            $this->addFlash('danger', '発注書が見つかりません。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $items = $itemRepository->findAwaitingByOrder($order);
        $itemsByManifestationId = [];
        foreach ($items as $item) {
            $manifestation = $item->getManifestation();
            if ($manifestation) {
                $itemsByManifestationId[$manifestation->getId()] = $item;
            }
        }

        $applied = 0;
        foreach ($ids as $id) {
            $item = $itemsByManifestationId[$id] ?? null;
            if ($item === null) {
                continue;
            }
            $manifestation = $item->getManifestation();
            if ($manifestation === null) {
                continue;
            }
            $workflow = $workflowRegistry->get($manifestation, 'manifestation');
            if ($workflow->can($manifestation, 'order')) {
                $workflow->apply($manifestation, 'order');
                $applied++;
            }
        }

        if ($applied === 0) {
            $this->addFlash('warning', '発注対象がありません。');
            return $this->redirectToRoute('app_acquisition_order', ['order_id' => $orderId]);
        }

        $order->setStatus(ManifestationOrder::STATUS_ORDERED);
        if ($order->getOrderedAt() === null) {
            $order->setOrderedAt(new \DateTime());
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf('発注を登録しました（%d件）。', $applied));
        return $this->redirectToRoute('app_acquisition_receive', ['order_id' => $order->getId()]);
    }

    #[Route('/order/export', name: 'app_acquisition_order_export', methods: ['GET'])]
    public function exportOrder(
        Request $request,
        ManifestationOrderRepository $orderRepository,
        ManifestationOrderItemRepository $itemRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $orderId = $request->query->getInt('order_id');
        if ($orderId <= 0) {
            $this->addFlash('danger', '発注書を選択してください。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $order = $orderRepository->find($orderId);
        if ($order === null) {
            $this->addFlash('danger', '発注書が見つかりません。');
            return $this->redirectToRoute('app_acquisition_order');
        }

        $items = $itemRepository->findByOrderWithManifestations($order);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('発注書');
        $sheet->setCellValue('A1', '発注番号');
        $sheet->setCellValue('B1', $order->getOrderNumber());
        $sheet->setCellValue('A2', '発注先');
        $sheet->setCellValue('B2', $order->getVendor());
        $sheet->setCellValue('A3', '発注金額');
        $sheet->setCellValue('B3', $order->getOrderAmount());
        $sheet->setCellValue('A4', '納品予定日');
        $sheet->setCellValue('B4', $order->getDeliveryDueDate()?->format('Y-m-d'));
        $sheet->setCellValue('A5', '見積番号');
        $sheet->setCellValue('B5', $order->getEstimateNumber());
        $sheet->setCellValue('A6', '発注先担当者');
        $sheet->setCellValue('B6', $order->getVendorContact());
        $sheet->setCellValue('A7', '発注元担当者');
        $sheet->setCellValue('B7', $order->getOrderContact());
        $sheet->setCellValue('A8', 'その他管理番号');
        $sheet->setCellValue('B8', $order->getExternalReference());
        $sheet->setCellValue('A9', 'メモ');
        $sheet->setCellValue('B9', $order->getMemo());

        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('明細');
        $detailSheet->setCellValue('A1', '識別子');
        $detailSheet->setCellValue('B1', 'タイトル');
        $detailSheet->setCellValue('C1', '状態');

        $row = 2;
        foreach ($items as $item) {
            $manifestation = $item->getManifestation();
            if ($manifestation === null) {
                continue;
            }
            $detailSheet->setCellValue('A' . $row, $manifestation->getIdentifier());
            $detailSheet->setCellValue('B' . $row, $manifestation->getTitle());
            $detailSheet->setCellValue('C' . $row, $manifestation->getStatus1());
            $row++;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'order_export_');
        if ($tempFile === false) {
            $this->addFlash('danger', 'ファイルの作成に失敗しました。');
            return $this->redirectToRoute('app_acquisition_order', ['order_id' => $orderId]);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $order->setExportedAt(new \DateTime());
        $entityManager->flush();

        $fileName = 'order_' . $order->getOrderNumber() . '.xlsx';
        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/receive', name: 'app_acquisition_receive', methods: ['GET'])]
    public function receive(
        Request $request,
        ManifestationOrderRepository $orderRepository
    ): Response {
        $orders = $orderRepository->findBy(
            ['status' => [ManifestationOrder::STATUS_ORDERED, ManifestationOrder::STATUS_COMPLETED]],
            ['ordered_at' => 'DESC']
        );
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

    // order number is provided by the user
}
