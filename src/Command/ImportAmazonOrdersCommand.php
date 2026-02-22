<?php

namespace App\Command;

use App\Service\AmazonImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:amazon-orders',
    description: 'Amazon注文ファイルから資料をインポートします。',
)]
class ImportAmazonOrdersCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(private AmazonImportService $amazonImportService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('zip', InputArgument::REQUIRED, 'Amazon注文ZIPファイルのパス')
            ->addOption('kindle-file', null, InputOption::VALUE_REQUIRED, 'Kindle向けCSVファイルのパス')
            ->addOption('only-isbn-asin', null, InputOption::VALUE_NONE, 'ISBN/ASINのみ取り込む');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $zipPath = (string) $input->getArgument('zip');
        $kindlePath = $input->getOption('kindle-file');

        if ($zipPath === '' || !is_file($zipPath)) {
            $io->error('ZIPファイルが見つかりません。');
            return Command::FAILURE;
        }
        if ($kindlePath !== null && !is_file($kindlePath)) {
            $io->error('Kindleファイルが見つかりません。');
            return Command::FAILURE;
        }

        $zipFile = $this->buildUploadedFile($zipPath);
        $kindleFile = $kindlePath ? $this->buildUploadedFile($kindlePath) : null;
        $onlyIsbnAsin = (bool) $input->getOption('only-isbn-asin');

        try {
            $result = $this->amazonImportService->processFile($zipFile, $onlyIsbnAsin, $kindleFile);
        } catch (\Throwable $e) {
            $io->error('インポート中にエラーが発生しました: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'インポート完了: 成功 %d件 / スキップ %d件 / エラー %d件',
            $result['success'],
            $result['skipped'],
            $result['errors']
        ));

        if (!empty($result['errorMessages'])) {
            $io->section('エラー詳細');
            foreach ($result['errorMessages'] as $message) {
                $io->writeln('- ' . $message);
            }
        }

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
