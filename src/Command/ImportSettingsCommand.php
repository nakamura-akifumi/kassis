<?php

namespace App\Command;

use App\Repository\CodeRepository;
use App\Service\SettingsFileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'app:import:settings',
    description: '設定（コード/貸出グループ/貸出条件）をファイルからインポートします。',
)]
class ImportSettingsCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(
        private SettingsFileService $settingsFileService,
        private CodeRepository $codeRepository,
        private ParameterBagInterface $params,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'インポートするXLSXファイルのパス');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('file');

        if ($path === '' || !is_file($path)) {
            $io->error('ファイルが見つかりません。');
            return Command::FAILURE;
        }

        $type1Options = $this->getType1Options();
        $memberGroupOptions = $this->buildMemberGroupChoices();
        $codeTypeOptions = $this->params->has('app.code.types') ? (array) $this->params->get('app.code.types') : [];
        $allGroupLabel = $this->getAllGroupLabel();

        $uploadedFile = $this->buildUploadedFile($path);
        $result = $this->settingsFileService->importFromFile(
            $uploadedFile,
            $type1Options,
            $memberGroupOptions,
            $codeTypeOptions,
            $allGroupLabel
        );

        if ($result['errorMessages'] !== []) {
            $io->section('エラー');
            foreach ($result['errorMessages'] as $message) {
                $io->writeln('- ' . $message);
            }
        }
        if ($result['warningMessages'] !== []) {
            $io->section('警告');
            foreach ($result['warningMessages'] as $message) {
                $io->writeln('- ' . $message);
            }
        }

        $io->success(sprintf(
            'インポート完了: コード 新規 %d / 更新 %d, グループ 新規 %d / 更新 %d, 条件 新規 %d / 更新 %d (スキップ: %d, エラー: %d)',
            $result['createdCodes'],
            $result['updatedCodes'],
            $result['createdGroups'],
            $result['updatedGroups'],
            $result['createdConditions'],
            $result['updatedConditions'],
            $result['skipped'],
            $result['errors']
        ));

        return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
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

    private function getAllGroupLabel(): string
    {
        return $this->translator->trans('Model.LoanGroup.values.all_group_members_identifier');
    }

    /**
     * @return array<string, string> label => value
     */
    private function buildMemberGroupChoices(): array
    {
        $group1Choices = $this->params->has('app.member.group1') ? (array) $this->params->get('app.member.group1') : [];
        $group1Choices = array_values(array_filter($group1Choices, static fn($value) => trim((string) $value) !== ''));
        $choices = [];
        foreach ($group1Choices as $value) {
            $key = 'Model.Member.values.Group1.' . $value;
            $label = $this->translator->trans($key);
            if ($label === $key) {
                $label = $value;
            }
            $choices[$label] = $value;
        }
        return $choices;
    }
}
