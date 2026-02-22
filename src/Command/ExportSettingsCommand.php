<?php

namespace App\Command;

use App\Repository\CodeRepository;
use App\Repository\LoanConditionRepository;
use App\Repository\LoanGroupRepository;
use App\Service\SettingsFileService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'app:export:settings',
    description: '設定（コード/貸出グループ/貸出条件）をXLSXでエクスポートします。',
)]
class ExportSettingsCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(
        private CodeRepository $codeRepository,
        private LoanGroupRepository $loanGroupRepository,
        private LoanConditionRepository $loanConditionRepository,
        private SettingsFileService $settingsFileService,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('output', InputArgument::REQUIRED, '出力ファイルパス、または出力先ディレクトリ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $codes = $this->codeRepository->findBy([], ['type' => 'ASC', 'display_order' => 'ASC', 'identifier' => 'ASC']);
        $groups = $this->loanGroupRepository->findBy([], ['name' => 'ASC']);
        $conditions = $this->loanConditionRepository->findBy([], ['id' => 'DESC']);
        $type1Options = $this->getType1Options();
        $allGroupLabel = $this->translator->trans('Model.LoanGroup.values.all_group_members_identifier');

        try {
            $tempFile = $this->settingsFileService->generateExportFile($codes, $groups, $conditions, $type1Options, $allGroupLabel);
            $defaultName = sprintf('settings_%s.xlsx', date('Y-m-d_H-i-s'));
            $outputPath = $this->resolveOutputPath((string) $input->getArgument('output'), $defaultName);
            $this->writeTempFile($tempFile, $outputPath);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('エクスポートが完了しました。');
        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> identifier => label
     */
    private function getType1Options(): array
    {
        $codes = $this->codeRepository->findBy(['type' => 'manifestation_type1'], ['display_order' => 'ASC', 'identifier' => 'ASC']);
        $options = [];
        foreach ($codes as $code) {
            $label = $code->getDisplayname();
            if ($label === null || trim($label) === '') {
                $label = $code->getIdentifier();
            }
            $options[$code->getIdentifier()] = $label;
        }
        return $options;
    }
}
