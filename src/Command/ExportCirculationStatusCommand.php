<?php

namespace App\Command;

use App\Repository\CheckoutRepository;
use App\Repository\ReservationRepository;
use App\Service\CirculationExportService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export:circulation-status',
    description: '予約/貸出/返却の一覧をエクスポートします。',
)]
class ExportCirculationStatusCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(
        private ReservationRepository $reservationRepository,
        private CheckoutRepository $checkoutRepository,
        private CirculationExportService $exportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('output', InputArgument::REQUIRED, '出力ファイルパス、または出力先ディレクトリ')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'reserve | checkout | return', 'reserve');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = (string) $input->getOption('type');
        $allowed = ['reserve', 'checkout', 'return'];
        if (!in_array($type, $allowed, true)) {
            $io->error('type は reserve / checkout / return のいずれかを指定してください。');
            return Command::FAILURE;
        }

        $reservations = [];
        $checkouts = [];
        if ($type === 'reserve') {
            $reservations = $this->reservationRepository->findRecent(50);
        } elseif ($type === 'checkout') {
            $checkouts = $this->checkoutRepository->findRecentActive(50);
        } else {
            $checkouts = $this->checkoutRepository->findRecentReturned(50);
        }

        try {
            $tempFile = $this->exportService->generateStatusExportFile($type, $reservations, $checkouts);
            $defaultName = sprintf('circulation_%s_%s.xlsx', $type, date('Y-m-d_H-i-s'));
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
