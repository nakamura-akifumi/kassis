<?php

namespace App\Service;

use App\Entity\Code;
use App\Entity\LoanCondition;
use App\Entity\LoanGroup;
use App\Repository\CodeRepository;
use App\Repository\LoanConditionRepository;
use App\Repository\LoanGroupRepository;
use App\Repository\LoanGroupType1Repository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SettingsFileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CodeRepository $codeRepository,
        private LoanGroupRepository $loanGroupRepository,
        private LoanGroupType1Repository $loanGroupType1Repository,
        private LoanConditionRepository $loanConditionRepository,
    ) {
    }

    /**
     * @param Code[] $codes
     * @param LoanGroup[] $groups
     * @param LoanCondition[] $conditions
     * @param array<string, string> $type1Options identifier => label
     */
    public function generateExportFile(array $codes, array $groups, array $conditions, array $type1Options, string $allGroupLabel): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->fillCodeSheet($spreadsheet, $codes);
        $this->fillLoanGroupSheet($spreadsheet, $groups, $type1Options);
        $this->fillLoanConditionSheet($spreadsheet, $conditions, $allGroupLabel);

        $tempFile = tempnam(sys_get_temp_dir(), 'settings_export_');
        if ($tempFile === false) {
            throw new RuntimeException('一時ファイルの作成に失敗しました。');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @param array<string, string> $type1Options identifier => label
     * @param array<string, string> $memberGroupOptions label => value
     * @param string[] $codeTypeOptions
     * @return array{
     *  createdCodes:int,updatedCodes:int,
     *  createdGroups:int,updatedGroups:int,
     *  createdConditions:int,updatedConditions:int,
     *  skipped:int,errors:int,warningMessages:string[],errorMessages: string[]
     * }
     */
    public function importFromFile(
        UploadedFile $file,
        array $type1Options,
        array $memberGroupOptions,
        array $codeTypeOptions,
        string $allGroupLabel
    ): array {
        $result = [
            'createdCodes' => 0,
            'updatedCodes' => 0,
            'createdGroups' => 0,
            'updatedGroups' => 0,
            'createdConditions' => 0,
            'updatedConditions' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warningMessages' => [],
            'errorMessages' => [],
        ];

        try {
            $spreadsheet = $this->loadSpreadsheet($file);
            $codeSheet = $this->findSheet($spreadsheet, 'コード設定');
            $groupSheet = $this->findSheet($spreadsheet, '貸出グループ');
            $conditionSheet = $this->findSheet($spreadsheet, '貸出条件');

            if ($codeSheet === null || $groupSheet === null || $conditionSheet === null) {
                $result['errors']++;
                $result['errorMessages'][] = 'シート「コード設定」「貸出グループ」「貸出条件」の全てが必要です。';
                return $result;
            }

            $codeRows = $codeSheet->toArray(null, true, true, true);
            $groupRows = $groupSheet->toArray(null, true, true, true);
            $conditionRows = $conditionSheet->toArray(null, true, true, true);

            $this->importCodes($codeRows, $codeTypeOptions, $result);
            if ($result['errors'] > 0) {
                return $result;
            }

            $groupMap = $this->importLoanGroups($groupRows, $type1Options, $allGroupLabel, $result);
            if ($result['errors'] > 0) {
                return $result;
            }

            $this->importLoanConditions($conditionRows, $memberGroupOptions, $allGroupLabel, $groupMap, $result);
            if ($result['errors'] > 0) {
                return $result;
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
     * @param Code[] $codes
     */
    private function fillCodeSheet(Spreadsheet $spreadsheet, array $codes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('コード設定');

        $headers = ['種別', '識別子', '値', '表示名', '表示順'];
        foreach ($headers as $i => $label) {
            $sheet->setCellValue([$i + 1, 1], $label);
        }

        $row = 2;
        foreach ($codes as $code) {
            $sheet->setCellValue([1, $row], $code->getType());
            $sheet->setCellValue([2, $row], $code->getIdentifier());
            $sheet->setCellValue([3, $row], $code->getValue());
            $sheet->setCellValue([4, $row], $code->getDisplayname());
            $sheet->setCellValue([5, $row], $code->getDisplayOrder());
            $row++;
        }

        $this->styleHeader($sheet, count($headers));
    }

    /**
     * @param LoanGroup[] $groups
     * @param array<string, string> $type1Options
     */
    private function fillLoanGroupSheet(Spreadsheet $spreadsheet, array $groups, array $type1Options): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('貸出グループ');

        $headers = ['貸出グループ名', '分類1(識別子,カンマ区切り)'];
        foreach ($headers as $i => $label) {
            $sheet->setCellValue([$i + 1, 1], $label);
        }

        $row = 2;
        foreach ($groups as $group) {
            $type1Identifiers = $group->getType1Identifiers();
            $type1Identifiers = array_values(array_filter($type1Identifiers, static fn($value) => trim((string) $value) !== ''));
            $sheet->setCellValue([1, $row], $group->getName());
            $sheet->setCellValue([2, $row], implode(',', $type1Identifiers));
            $row++;
        }

        $this->styleHeader($sheet, count($headers));
    }

    /**
     * @param LoanCondition[] $conditions
     */
    private function fillLoanConditionSheet(Spreadsheet $spreadsheet, array $conditions, string $allGroupLabel): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('貸出条件');

        $headers = [
            '貸出グループ名',
            '利用者グループ',
            '貸出数上限',
            '貸出期間(日)',
            '更新回数上限',
            '予約数上限',
            '閉館日調整(1/0)',
        ];
        foreach ($headers as $i => $label) {
            $sheet->setCellValue([$i + 1, 1], $label);
        }

        $row = 2;
        foreach ($conditions as $condition) {
            $groupName = $condition->getLoanGroup()?->getName() ?? $allGroupLabel;
            $sheet->setCellValue([1, $row], $groupName);
            $sheet->setCellValue([2, $row], $condition->getMemberGroup());
            $sheet->setCellValue([3, $row], $condition->getLoanLimit());
            $sheet->setCellValue([4, $row], $condition->getLoanPeriod());
            $sheet->setCellValue([5, $row], $condition->getRenewLimit());
            $sheet->setCellValue([6, $row], $condition->getReservationLimit());
            $sheet->setCellValue([7, $row], $condition->isAdjustDueOnClosedDay() ? 1 : 0);
            $row++;
        }

        $this->styleHeader($sheet, count($headers));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $typeOptions
     */
    private function importCodes(array $rows, array $typeOptions, array &$result): void
    {
        if (count($rows) < 2) {
            $result['warningMessages'][] = 'コード設定シートにデータ行がありません。';
            return;
        }

        $headerRow = $rows[1];
        $colMap = $this->buildCodeColumnMap($headerRow);
        if ($colMap['type'] === null || $colMap['identifier'] === null || $colMap['value'] === null) {
            $result['errors']++;
            $result['errorMessages'][] = 'コード設定シートのヘッダーが不正です。';
            return;
        }

        for ($i = 2, $iMax = count($rows); $i <= $iMax; $i++) {
            $row = $rows[$i] ?? null;
            if ($row === null) {
                continue;
            }

            $type = $this->getCellValue($row, $colMap['type']);
            $identifier = $this->getCellValue($row, $colMap['identifier']);
            $value = $this->getCellValue($row, $colMap['value']);
            $displayname = $this->getCellValue($row, $colMap['displayname']);
            $displayOrderRaw = $this->getCellValue($row, $colMap['display_order']);
            $displayOrder = $displayOrderRaw !== null ? (int) $displayOrderRaw : 0;

            if ($this->isBlank($type) && $this->isBlank($identifier) && $this->isBlank($value) && $this->isBlank($displayname) && $displayOrderRaw === null) {
                $result['skipped']++;
                continue;
            }

            if ($this->isBlank($type) || $this->isBlank($identifier) || $this->isBlank($value)) {
                $result['errors']++;
                $result['errorMessages'][] = sprintf('%d行目: 種別/識別子/値は必須です。', $i);
                continue;
            }

            if ($typeOptions !== [] && !in_array($type, $typeOptions, true)) {
                $result['errors']++;
                $result['errorMessages'][] = sprintf('%d行目: 種別が不正です。', $i);
                continue;
            }

            if (strlen((string) $value) > 32) {
                $result['errors']++;
                $result['errorMessages'][] = sprintf('%d行目: 値は32文字以内で入力してください。', $i);
                continue;
            }

            $code = $this->codeRepository->findOneBy([
                'type' => $type,
                'identifier' => $identifier,
            ]);

            if ($code === null) {
                $code = new Code();
                $code->setType($type);
                $code->setIdentifier($identifier);
                $this->entityManager->persist($code);
                $result['createdCodes']++;
            } else {
                $result['updatedCodes']++;
            }

            $code->setValue((string) $value);
            $code->setDisplayname($displayname !== null && $displayname !== '' ? $displayname : null);
            $code->setDisplayOrder($displayOrder);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $type1Options
     * @return array<string, LoanGroup> name => entity
     */
    private function importLoanGroups(array $rows, array $type1Options, string $allGroupLabel, array &$result): array
    {
        if (count($rows) < 2) {
            $result['warningMessages'][] = '貸出グループシートにデータ行がありません。';
            return [];
        }

        $headerRow = $rows[1];
        $colMap = $this->buildLoanGroupColumnMap($headerRow);
        if ($colMap['name'] === null) {
            $result['errors']++;
            $result['errorMessages'][] = '貸出グループシートのヘッダーが不正です。';
            return [];
        }

        $availableType1 = array_keys($type1Options);
        $seenType1 = [];
        $groupMap = [];

        for ($i = 2, $iMax = count($rows); $i <= $iMax; $i++) {
            $row = $rows[$i] ?? null;
            if ($row === null) {
                continue;
            }

            $name = $this->getCellValue($row, $colMap['name']);
            $type1Raw = $this->getCellValue($row, $colMap['type1']);

            if ($this->isBlank($name) && $this->isBlank($type1Raw)) {
                $result['skipped']++;
                continue;
            }

            if ($this->isBlank($name)) {
                $result['errors']++;
                $result['errorMessages'][] = sprintf('%d行目: 貸出グループ名は必須です。', $i);
                continue;
            }

            $name = trim((string) $name);
            $type1Identifiers = $this->parseIdentifiers($type1Raw);

            if ($name === $allGroupLabel) {
                if ($type1Identifiers !== []) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: "%s" は分類1を指定できません。', $i, $allGroupLabel);
                }
                $group = $this->getOrCreateAllLoanGroup($allGroupLabel);
                $groupMap[$group->getName()] = $group;
                if ($result['errors'] > 0) {
                    continue;
                }
                if ($group->getId() === null) {
                    $result['createdGroups']++;
                } else {
                    $result['updatedGroups']++;
                }
                continue;
            }

            foreach ($type1Identifiers as $identifier) {
                if ($availableType1 !== [] && !in_array($identifier, $availableType1, true)) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 分類1 "%s" が不正です。', $i, $identifier);
                }
                if (isset($seenType1[$identifier])) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 分類1 "%s" が複数の貸出グループに指定されています。', $i, $identifier);
                }
                $seenType1[$identifier] = $name;
            }

            $group = $this->loanGroupRepository->findOneBy(['name' => $name]);
            $isNew = false;
            if ($group === null) {
                $group = new LoanGroup();
                $group->setName($name);
                $this->entityManager->persist($group);
                $isNew = true;
            }

            if ($type1Identifiers !== []) {
                $conflicts = $this->loanGroupType1Repository->findConflicts($type1Identifiers, $group->getId());
                if ($conflicts !== []) {
                    foreach ($conflicts as $identifier => $groupName) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 分類1 "%s" は既に貸出グループ "%s" に割り当て済みです。', $i, $identifier, $groupName);
                    }
                }
            }

            if ($result['errors'] > 0) {
                continue;
            }

            $group->setType1Identifiers($type1Identifiers);
            if ($isNew) {
                $result['createdGroups']++;
            } else {
                $result['updatedGroups']++;
            }
            $groupMap[$group->getName()] = $group;
        }

        return $groupMap;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $memberGroupOptions
     * @param array<string, LoanGroup> $groupMap
     */
    private function importLoanConditions(array $rows, array $memberGroupOptions, string $allGroupLabel, array $groupMap, array &$result): void
    {
        if (count($rows) < 2) {
            $result['warningMessages'][] = '貸出条件シートにデータ行がありません。';
            return;
        }

        $headerRow = $rows[1];
        $colMap = $this->buildLoanConditionColumnMap($headerRow);
        foreach (['loan_group', 'member_group', 'loan_limit', 'loan_period', 'renew_limit', 'reservation_limit'] as $required) {
            if ($colMap[$required] === null) {
                $result['errors']++;
                $result['errorMessages'][] = '貸出条件シートのヘッダーが不正です。';
                return;
            }
        }

        $memberGroupMap = $this->buildMemberGroupMap($memberGroupOptions);

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

            $loanGroup = $this->resolveLoanGroup($loanGroupValue, $groupMap, $allGroupLabel);
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
                $result['createdConditions']++;
            } else {
                $result['updatedConditions']++;
            }

            $condition->setLoanLimit($loanLimit);
            $condition->setLoanPeriod($loanPeriod);
            $condition->setRenewLimit($renewLimit);
            $condition->setReservationLimit($reservationLimit);
            $condition->setAdjustDueOnClosedDay($adjustDueOnClosedDay);
        }
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $columnCount): void
    {
        $lastColLetter = Coordinate::stringFromColumnIndex($columnCount);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');

        for ($i = 1; $i <= $columnCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $path = $file->getPathname();
        $reader = IOFactory::createReader('Xlsx');
        return $reader->load($path);
    }

    private function findSheet(Spreadsheet $spreadsheet, string $title): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (trim((string) $sheet->getTitle()) === $title) {
                return $sheet;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $headerRow
     * @return array<string, string|null>
     */
    private function buildCodeColumnMap(array $headerRow): array
    {
        $map = [
            'type' => null,
            'identifier' => null,
            'value' => null,
            'displayname' => null,
            'display_order' => null,
        ];
        $labelMap = [
            'type' => 'type',
            'Type' => 'type',
            '種別' => 'type',
            'identifier' => 'identifier',
            'Identifier' => 'identifier',
            '識別子' => 'identifier',
            'value' => 'value',
            'Value' => 'value',
            '値' => 'value',
            'displayname' => 'displayname',
            'Displayname' => 'displayname',
            'DisplayName' => 'displayname',
            '表示名' => 'displayname',
            'display_order' => 'display_order',
            'DisplayOrder' => 'display_order',
            '表示順' => 'display_order',
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
     * @param array<string, mixed> $headerRow
     * @return array<string, string|null>
     */
    private function buildLoanGroupColumnMap(array $headerRow): array
    {
        $map = [
            'name' => null,
            'type1' => null,
        ];
        $labelMap = [
            '貸出グループ名' => 'name',
            'loan_group' => 'name',
            'LoanGroup' => 'name',
            '分類1' => 'type1',
            '分類1(識別子,カンマ区切り)' => 'type1',
            'type1' => 'type1',
            'Type1' => 'type1',
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
     * @param array<string, mixed> $headerRow
     * @return array<string, string|null>
     */
    private function buildLoanConditionColumnMap(array $headerRow): array
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
            '貸出グループ名' => 'loan_group',
            'loan_group' => 'loan_group',
            'LoanGroup' => 'loan_group',
            '利用者グループ' => 'member_group',
            'member_group' => 'member_group',
            'MemberGroup' => 'member_group',
            '貸出数上限' => 'loan_limit',
            'loan_limit' => 'loan_limit',
            'LoanLimit' => 'loan_limit',
            '貸出期間(日)' => 'loan_period',
            '貸出期間' => 'loan_period',
            'loan_period' => 'loan_period',
            'LoanPeriod' => 'loan_period',
            '更新回数上限' => 'renew_limit',
            'renew_limit' => 'renew_limit',
            'RenewLimit' => 'renew_limit',
            '予約数上限' => 'reservation_limit',
            'reservation_limit' => 'reservation_limit',
            'ReservationLimit' => 'reservation_limit',
            '閉館日調整(1/0)' => 'adjust_due_on_closed_day',
            '閉館日調整' => 'adjust_due_on_closed_day',
            'adjust_due_on_closed_day' => 'adjust_due_on_closed_day',
            'AdjustDueOnClosedDay' => 'adjust_due_on_closed_day',
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
     * @param array<string, LoanGroup> $groupMap
     */
    private function resolveLoanGroup(string $value, array $groupMap, string $allGroupLabel): ?LoanGroup
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (mb_strtolower($trimmed) === 'all' || $trimmed === $allGroupLabel) {
            return $this->getOrCreateAllLoanGroup($allGroupLabel);
        }
        if (isset($groupMap[$trimmed])) {
            return $groupMap[$trimmed];
        }
        return $this->loanGroupRepository->findOneBy(['name' => $trimmed]);
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

    /**
     * @return string[]
     */
    private function parseIdentifiers(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $raw = trim($value);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\\s,;\\n\\r]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }
        $unique = [];
        foreach ($parts as $part) {
            $val = trim($part);
            if ($val !== '') {
                $unique[$val] = true;
            }
        }
        return array_keys($unique);
    }
}
