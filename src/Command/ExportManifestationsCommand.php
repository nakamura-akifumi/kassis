<?php

namespace App\Command;

use App\Repository\ManifestationRepository;
use App\Service\FileService;
use App\Service\ManifestationFileColumns;
use App\Service\ManifestationSearchQuery;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'app:export:manifestations',
    description: '資料（Manifestation）をCSV/XLSXでエクスポートします。',
)]
class ExportManifestationsCommand extends Command
{
    use CommandFileHelperTrait;

    public function __construct(
        private ManifestationRepository $manifestationRepository,
        private FileService $fileService,
        private ParameterBagInterface $params,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('output', InputArgument::REQUIRED, '出力ファイルパス、または出力先ディレクトリ')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'csv または xlsx', 'xlsx')
            ->addOption('columns', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'エクスポートする列キー (複数指定可)')
            ->addOption('all', null, InputOption::VALUE_NONE, '全項目を出力する')
            ->addOption('preset', null, InputOption::VALUE_REQUIRED, 'プリセット名 (full/min または parameters.yaml で定義したもの)')
            ->addOption('q', null, InputOption::VALUE_REQUIRED, '検索キーワード')
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, '識別子で検索')
            ->addOption('external-id1', null, InputOption::VALUE_REQUIRED, '外部識別子1で検索')
            ->addOption('type1', null, InputOption::VALUE_REQUIRED, '分類1で検索')
            ->addOption('type2', null, InputOption::VALUE_REQUIRED, '分類2で検索')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'ソートキー')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'simple | multi | advanced', 'simple');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = strtolower((string) $input->getOption('format'));
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            $io->error('format は csv または xlsx を指定してください。');
            return Command::FAILURE;
        }

        $columnsOption = $this->normalizeColumns($input->getOption('columns'));
        $presetName = $input->getOption('preset');
        $all = (bool) $input->getOption('all');
        if ($all && ($presetName !== null || $columnsOption !== [])) {
            $io->error('--all は --preset/--columns と併用できません。');
            return Command::FAILURE;
        }
        if ($presetName !== null && $columnsOption !== []) {
            $io->error('--preset は --columns と併用できません。');
            return Command::FAILURE;
        }

        $columns = $columnsOption;
        if ($all) {
            $columns = $this->getAllColumns();
        } elseif ($presetName !== null) {
            $presetColumns = $this->getPresetColumns($presetName);
            if ($presetColumns === null) {
                $io->error(sprintf('プリセット "%s" が見つかりません。', $presetName));
                $this->printAvailablePresets($io);
                return Command::FAILURE;
            }
            $columns = $presetColumns;
        } elseif ($columns === []) {
            $defaultPreset = $this->params->has('app.export.manifestations.default_preset')
                ? $this->params->get('app.export.manifestations.default_preset')
                : null;
            if (is_string($defaultPreset) && $defaultPreset !== '') {
                $presetColumns = $this->getPresetColumns($defaultPreset);
                if ($presetColumns !== null) {
                    $columns = $presetColumns;
                }
            }
        }

        $params = [
            'q' => $input->getOption('q'),
            'identifier' => $input->getOption('identifier'),
            'external_identifier1' => $input->getOption('external-id1'),
            'type1' => $input->getOption('type1'),
            'type2' => $input->getOption('type2'),
            'sort' => $input->getOption('sort'),
            'mode' => $input->getOption('mode'),
        ];

        $query = ManifestationSearchQuery::fromRequest($params);
        $manifestations = $this->manifestationRepository->searchByQuery($query);

        if ($manifestations === []) {
            $io->warning('対象データがありません。');
        }

        try {
            $tempFile = $this->fileService->generateExportFile($manifestations, $format, $columns);
            $defaultName = sprintf('manifestations_%s.%s', date('Y-m-d_H-i-s'), $format);
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
     * @param array<int, string> $columns
     * @return string[]
     */
    private function normalizeColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            foreach (preg_split('/\s*,\s*/', $column, -1, PREG_SPLIT_NO_EMPTY) as $item) {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return string[]
     */
    private function getAllColumns(): array
    {
        $columns = ManifestationFileColumns::getExportColumns($this->translator);
        return array_keys($columns);
    }

    /**
     * @return string[]|null
     */
    private function getPresetColumns(string $presetName): ?array
    {
        if ($presetName === 'full') {
            return $this->getAllColumns();
        }
        if ($presetName === 'min') {
            return [];
        }

        if (!$this->params->has('app.export.manifestations.presets')) {
            return null;
        }

        $presets = (array) $this->params->get('app.export.manifestations.presets');
        if (!array_key_exists($presetName, $presets)) {
            return null;
        }

        $columns = $presets[$presetName];
        if (!is_array($columns)) {
            return null;
        }

        return array_values(array_filter(array_map('strval', $columns), static fn($value) => $value !== ''));
    }

    private function printAvailablePresets(SymfonyStyle $io): void
    {
        $io->section('利用可能なプリセット');
        $io->writeln('- full');
        $io->writeln('- min');

        if (!$this->params->has('app.export.manifestations.presets')) {
            return;
        }

        $presets = (array) $this->params->get('app.export.manifestations.presets');
        if ($presets === []) {
            return;
        }

        foreach (array_keys($presets) as $name) {
            $io->writeln('- ' . $name);
        }
    }
}
