<?php

namespace App\Service;

use App\Entity\LoanCondition;
use App\Entity\LoanGroup;
use App\Repository\LoanConditionRepository;
use App\Repository\LoanGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LoanConditionFileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanGroupRepository $loanGroupRepository,
        private LoanConditionRepository $loanConditionRepository,
    ) {
    }

    /**
     * @param LoanCondition[] $conditions
     */
    public function generateExportFile(array $conditions, string $format, string $allGroupLabel): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $columns = $this->getExportColumns();
        $colIndex = 1;
        foreach ($columns as $column) {
            $sheet->setCellValue([$colIndex, 1], $column['label']);
            $colIndex++;
        }

        $row = 2;
        foreach ($conditions as $condition) {
            $loanGroup = $condition->getLoanGroup();
            $groupName = $loanGroup?->getName() ?? $allGroupLabel;
            $colIndex = 1;
            foreach ($columns as $column) {
                $value = match ($column['key']) {
                    'loan_group' => $groupName,
                    'member_group' => $condition->getMemberGroup(),
                    'loan_limit' => $condition->getLoanLimit(),
                    'loan_period' => $condition->getLoanPeriod(),
                    'renew_limit' => $condition->getRenewLimit(),
                    'reservation_limit' => $condition->getReservationLimit(),
                    'adjust_due_on_closed_day' => $condition->isAdjustDueOnClosedDay() ? 1 : 0,
                    default => null,
                };
                $sheet->setCellValue([$colIndex, $row], $value);
                $colIndex++;
            }
            $row++;
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($columns));
        if ($format === 'xlsx') {
            $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            for ($i = 1, $iMax = count($columns); $i <= $iMax; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'loan_condition_export_');
        if ($tempFile === false) {
            throw new RuntimeException('一時ファイルの作成に失敗しました。');
        }

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setLineEnding("\n");
            $writer->setUseBOM(false);
        } else {
            $writer = new Xlsx($spreadsheet);
        }
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @param array<string, string> $memberGroupOptions label => value
     * @return array{created:int, updated:int, skipped:int, errors:int, errorMessages: string[]}
     */
    public function importFromFile(UploadedFile $file, array $memberGroupOptions, string $allGroupLabel): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errorMessages' => [],
        ];

        try {
            $spreadsheet = $this->loadSpreadsheet($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                $result['skipped']++;
                $result['errorMessages'][] = 'データ行がありません（ヘッダーのみ、または空ファイルです）。';
                return $result;
            }

            $headerRow = $rows[1];
            $colMap = $this->buildColumnMapFromHeader($headerRow);
            foreach (['loan_group', 'member_group', 'loan_limit', 'loan_period', 'renew_limit', 'reservation_limit'] as $required) {
                if ($colMap[$required] === null) {
                    $result['errors']++;
                    $result['errorMessages'][] = 'ヘッダーが不正です。エクスポートしたファイルを使用してください。';
                    return $result;
                }
            }

            $memberGroupMap = $this->buildMemberGroupMap($memberGroupOptions);
            $loanGroupMap = $this->buildLoanGroupMap();

            for ($i = 2, $iMax = count($rows); $i <= $iMax; $i++) {
                $row = $rows[$i] ?? null;
                if ($row === null) {
                    continue;
                }

                $loanGroupValue = $this->getCellValue($row, $colMap['loan_group']);
                $memberGroupValueRaw = $this->getCellValue($row, $colMap['member_group']);
                $loanLimitRaw = $this->getCellValue($row, $colMap['loan_limit']);
                $loanPeriodRaw = $this->getCellValue($row, $colMap['loan_period']);
                $renewLimitRaw = $this->getCellValue($row, $colMap['renew_limit']);
                $reservationLimitRaw = $this->getCellValue($row, $colMap['reservation_limit']);
                $adjustRaw = $this->getCellValue($row, $colMap['adjust_due_on_closed_day']);

                if ($this->isBlank($loanGroupValue)
                    && $this->isBlank($memberGroupValueRaw)
                    && $this->isBlank($loanLimitRaw)
                    && $this->isBlank($loanPeriodRaw)
                    && $this->isBlank($renewLimitRaw)
                    && $this->isBlank($reservationLimitRaw)
                    && $this->isBlank($adjustRaw)
                ) {
                    $result['skipped']++;
                    continue;
                }

                if ($this->isBlank($loanGroupValue) || $this->isBlank($memberGroupValueRaw)) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 貸出グループと利用者グループは必須です。', $i);
                    continue;
                }

                $memberGroupValue = $this->resolveMemberGroup($memberGroupValueRaw, $memberGroupMap);
                if ($memberGroupValue === null) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 利用者グループが不正です。', $i);
                    continue;
                }

                $loanGroup = $this->resolveLoanGroup($loanGroupValue, $loanGroupMap, $allGroupLabel);
                if ($loanGroup === null) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 貸出グループが見つかりません。', $i);
                    continue;
                }

                $loanLimit = $this->parseNonNegativeInt($loanLimitRaw);
                $loanPeriod = $this->parseNonNegativeInt($loanPeriodRaw);
                $renewLimit = $this->parseNonNegativeInt($renewLimitRaw);
                $reservationLimit = $this->parseNonNegativeInt($reservationLimitRaw);

                if ($loanLimit === null || $loanPeriod === null || $renewLimit === null || $reservationLimit === null) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 数値項目は0以上の整数で入力してください。', $i);
                    continue;
                }

                $adjustDueOnClosedDay = $this->parseBool($adjustRaw);

                $condition = $this->loanConditionRepository->findOneBy([
                    'loanGroup' => $loanGroup,
                    'member_group' => $memberGroupValue,
                ]);

                if ($condition === null) {
                    $condition = new LoanCondition();
                    $condition->setLoanGroup($loanGroup);
                    $condition->setMemberGroup($memberGroupValue);
                    $this->entityManager->persist($condition);
                    $result['created']++;
                } else {
                    $result['updated']++;
                }

                $condition->setLoanLimit($loanLimit);
                $condition->setLoanPeriod($loanPeriod);
                $condition->setRenewLimit($renewLimit);
                $condition->setReservationLimit($reservationLimit);
                $condition->setAdjustDueOnClosedDay($adjustDueOnClosedDay);
            }

            $this->entityManager->flush();
            return $result;
        } catch (\Throwable $e) {
            $result['errors']++;
            $result['errorMessages'][] = 'ファイルの読み込みに失敗しました: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * @return array<int, array{label: string, key: string}>
     */
    private function getExportColumns(): array
    {
        return [
            ['label' => '貸出グループ', 'key' => 'loan_group'],
            ['label' => '利用者グループ', 'key' => 'member_group'],
            ['label' => '貸出数上限', 'key' => 'loan_limit'],
            ['label' => '貸出期間(日)', 'key' => 'loan_period'],
            ['label' => '更新回数上限', 'key' => 'renew_limit'],
            ['label' => '予約数上限', 'key' => 'reservation_limit'],
            ['label' => '閉館日調整(1/0)', 'key' => 'adjust_due_on_closed_day'],
        ];
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $path = $file->getPathname();
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv') {
            $reader = IOFactory::createReader('Csv');
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            return $reader->load($path);
        }

        return IOFactory::load($path);
    }

    /**
     * @param array<string, mixed> $headerRow
     * @return array<string, string|null>
     */
    private function buildColumnMapFromHeader(array $headerRow): array
    {
        $map = [
            'loan_group' => null,
            'member_group' => null,
            'loan_limit' => null,
            'loan_period' => null,
            'renew_limit' => null,
            'reservation_limit' => null,
            'adjust_due_on_closed_day' => null,
        ];
        $labelMap = [
            'loan_group' => 'loan_group',
            'LoanGroup' => 'loan_group',
            '貸出グループ' => 'loan_group',
            'member_group' => 'member_group',
            'MemberGroup' => 'member_group',
            '利用者グループ' => 'member_group',
            'loan_limit' => 'loan_limit',
            'LoanLimit' => 'loan_limit',
            '貸出数上限' => 'loan_limit',
            'loan_period' => 'loan_period',
            'LoanPeriod' => 'loan_period',
            '貸出期間' => 'loan_period',
            '貸出期間(日)' => 'loan_period',
            'renew_limit' => 'renew_limit',
            'RenewLimit' => 'renew_limit',
            '更新回数上限' => 'renew_limit',
            'reservation_limit' => 'reservation_limit',
            'ReservationLimit' => 'reservation_limit',
            '予約数上限' => 'reservation_limit',
            'adjust_due_on_closed_day' => 'adjust_due_on_closed_day',
            'AdjustDueOnClosedDay' => 'adjust_due_on_closed_day',
            '閉館日調整' => 'adjust_due_on_closed_day',
            '閉館日調整(1/0)' => 'adjust_due_on_closed_day',
        ];

        foreach ($headerRow as $col => $value) {
            $label = trim((string) $value);
            if ($label === '') {
                continue;
            }
            if (isset($labelMap[$label])) {
                $map[$labelMap[$label]] = $col;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function getCellValue(array $row, ?string $col): ?string
    {
        if ($col === null) {
            return null;
        }
        $v = $row[$col] ?? null;
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function parseNonNegativeInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = trim($value);
        if ($str === '' || !preg_match('/^\d+$/', $str)) {
            return null;
        }
        return (int) $str;
    }

    private function parseBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'no' || $normalized === 'なし' || $normalized === '無') {
            return false;
        }
        if ($normalized === '1' || $normalized === 'true' || $normalized === 'yes' || $normalized === '適用' || $normalized === '有') {
            return true;
        }
        return filter_var($normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * @param array<string, string> $memberGroupOptions
     * @return array<string, string>
     */
    private function buildMemberGroupMap(array $memberGroupOptions): array
    {
        $map = [];
        foreach ($memberGroupOptions as $label => $value) {
            $labelKey = trim((string) $label);
            $valueKey = trim((string) $value);
            if ($labelKey !== '') {
                $map[$labelKey] = $value;
            }
            if ($valueKey !== '') {
                $map[$valueKey] = $value;
            }
        }
        return $map;
    }

    /**
     * @return array<string, LoanGroup>
     */
    private function buildLoanGroupMap(): array
    {
        $groups = $this->loanGroupRepository->findBy([], ['name' => 'ASC']);
        $map = [];
        foreach ($groups as $group) {
            $name = trim((string) $group->getName());
            if ($name !== '') {
                $map[$name] = $group;
            }
        }
        return $map;
    }

    /**
     * @param array<string, LoanGroup> $loanGroupMap
     */
    private function resolveLoanGroup(string $value, array $loanGroupMap, string $allGroupLabel): ?LoanGroup
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (mb_strtolower($trimmed) === 'all' || $trimmed === $allGroupLabel) {
            return $this->getOrCreateAllLoanGroup($allGroupLabel);
        }
        return $loanGroupMap[$trimmed] ?? null;
    }

    /**
     * @param array<string, string> $memberGroupMap
     */
    private function resolveMemberGroup(string $value, array $memberGroupMap): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if ($memberGroupMap === []) {
            return $trimmed;
        }
        return $memberGroupMap[$trimmed] ?? null;
    }

    private function getOrCreateAllLoanGroup(string $allGroupLabel): LoanGroup
    {
        $existing = $this->loanGroupRepository->findOneBy(['name' => $allGroupLabel]);
        if ($existing instanceof LoanGroup) {
            return $existing;
        }
        $group = new LoanGroup();
        $group->setName($allGroupLabel);
        $this->entityManager->persist($group);
        return $group;
    }
}
