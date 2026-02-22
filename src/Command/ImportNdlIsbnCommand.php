<?php

namespace App\Command;

use App\Service\NdlImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:ndl-isbn',
    description: 'ISBNからNDL検索で資料を取り込みます。',
)]
class ImportNdlIsbnCommand extends Command
{
    public function __construct(private NdlImportService $ndlImportService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('isbns', InputArgument::IS_ARRAY, 'ISBN（複数指定可）')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'ISBN一覧ファイルのパス（1行1件）')
            ->addOption('skip-existing', null, InputOption::VALUE_NEGATABLE, '既存のISBNはスキップする', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isbns = $input->getArgument('isbns');
        $file = $input->getOption('file');
        $skipExisting = (bool) $input->getOption('skip-existing');

        if ($file !== null) {
            if (!is_file($file)) {
                $io->error('ISBN一覧ファイルが見つかりません。');
                return Command::FAILURE;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $isbns = array_merge($isbns, $lines);
            }
        }

        $isbns = array_values(array_filter(array_map('trim', $isbns)));
        if ($isbns === []) {
            $io->error('ISBNを指定してください。');
            return Command::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($isbns as $isbn) {
            try {
                if ($skipExisting && $this->ndlImportService->findExistingByIsbn($isbn) !== null) {
                    $skipped++;
                    continue;
                }
                $manifestation = $this->ndlImportService->importByIsbn($isbn);
                if ($manifestation === null) {
                    $errors++;
                    $io->writeln(sprintf('取り込み失敗: %s', $isbn));
                } else {
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $io->writeln(sprintf('エラー: %s (%s)', $isbn, $e->getMessage()));
            }
        }

        $io->success(sprintf('取り込み完了: 新規 %d / スキップ %d / エラー %d', $created, $skipped, $errors));
        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
