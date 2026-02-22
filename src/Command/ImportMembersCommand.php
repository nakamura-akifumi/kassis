<?php

namespace App\Command;

use App\Service\MemberFileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:members',
    description: '利用者（Member）をファイルからインポートします。',
)]
class ImportMembersCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(private MemberFileService $memberFileService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'インポートするCSV/XLSXファイルのパス');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('file');

        if ($path === '' || !is_file($path)) {
            $io->error('ファイルが見つかりません。');
            return Command::FAILURE;
        }

        $uploadedFile = $this->buildUploadedFile($path);
        $result = $this->memberFileService->importMembersFromFile($uploadedFile);

        $io->success(sprintf(
            'インポート完了: 成功 %d件 / スキップ %d件 / エラー %d件',
            $result['success'],
            $result['skipped'],
            $result['errors']
        ));

        if ($result['errorMessages'] !== []) {
            $io->section('エラーメッセージ');
            foreach ($result['errorMessages'] as $message) {
                $io->writeln('- ' . $message);
            }
        }

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
