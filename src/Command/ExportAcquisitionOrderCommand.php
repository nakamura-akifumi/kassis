<?php

namespace App\Command;

use App\Repository\ManifestationOrderItemRepository;
use App\Repository\ManifestationOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export:acquisition-order',
    description: '発注書をXLSXでエクスポートします。',
)]
class ExportAcquisitionOrderCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(
        private ManifestationOrderRepository $orderRepository,
        private ManifestationOrderItemRepository $itemRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, '発注書ID')
            ->addArgument('output', InputArgument::REQUIRED, '出力ファイルパス、または出力先ディレクトリ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = (int) $input->getArgument('order-id');
        if ($orderId <= 0) {
            $io->error('order-id を指定してください。');
            return Command::FAILURE;
        }

        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            $io->error('発注書が見つかりません。');
            return Command::FAILURE;
        }

        $items = $this->itemRepository->findByOrderWithManifestations($order);

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
            $io->error('一時ファイルの作成に失敗しました。');
            return Command::FAILURE;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $order->setExportedAt(new \DateTime());
        $this->entityManager->flush();

        try {
            $defaultName = 'order_' . $order->getOrderNumber() . '.xlsx';
            $outputPath = $this->resolveOutputPath((string) $input->getArgument('output'), $defaultName);
            $this->writeTempFile($tempFile, $outputPath);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('エクスポートが完了しました。');
        return Command::SUCCESS;
    }
}
