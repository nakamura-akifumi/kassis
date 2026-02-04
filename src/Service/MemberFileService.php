<?php

namespace App\Service;

use App\Entity\Member;
use App\Repository\MemberRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MemberFileService
{
    public function __construct(
        private TranslatorInterface $t,
        private EntityManagerInterface $entityManager,
        private MemberRepository $memberRepository,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param Member[] $members
     * @param string[] $columns
     */
    public function generateExportFile(array $members, string $format, array $columns = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $allColumns = MemberFileColumns::getExportColumns($this->t);
        $required = MemberFileColumns::REQUIRED_EXPORT_KEYS;
        $selectedColumns = array_unique(array_merge($columns, $required));

        $colIndex = 1;
        foreach ($selectedColumns as $key) {
            if (isset($allColumns[$key])) {
                $sheet->setCellValue([$colIndex, 1], $allColumns[$key]['label']);
                $colIndex++;
            }
        }

        $row = 2;
        foreach ($members as $member) {
            $colIndex = 1;
            foreach ($selectedColumns as $key) {
                if (isset($allColumns[$key])) {
                    $getter = $allColumns[$key]['getter'];
                    $value = $member->$getter();
                    if ($value instanceof DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    $sheet->setCellValue([$colIndex, $row], $value);
                    $colIndex++;
                }
            }
            $row++;
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($selectedColumns));
        if ($format === 'xlsx') {
            $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            for ($i = 1, $iMax = count($selectedColumns); $i <= $iMax; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'member_export_');
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

    public function importMembersFromFile(UploadedFile $file): array
    {
        $result = [
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'errorMessages' => [],
        ];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();
            $highestColIndex = Coordinate::columnIndexFromString($highestCol);

            $headerMap = $this->buildHeaderMap($sheet, $highestColIndex);
            if ($headerMap === []) {
                $result['errors']++;
                $result['errorMessages'][] = 'ヘッダーが認識できませんでした。';
                return $result;
            }

            $allowedGroup1 = $this->getAllowedMemberValues('app.member.group1');
            $allowedRole = $this->getAllowedMemberValues('app.member.role');
            $allowedStatus = $this->getAllowedMemberValues('app.member.status');
            $group1LabelMap = $this->buildValueLabelMap($allowedGroup1, 'Model.Member.values.Group1.');
            $roleLabelMap = $this->buildValueLabelMap($allowedRole, 'Model.Member.values.Role.');

            $seenIdentifiers = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $rowData = $this->generateFreshRow();
                    $hasValue = false;
                    foreach ($headerMap as $colIndex => $importKey) {
                        $cellValue = $sheet->getCell([$colIndex, $row])->getValue();
                        if ($cellValue instanceof RichText) {
                            $cellValue = $cellValue->getPlainText();
                        }
                        if (!$this->isBlank($cellValue)) {
                            $hasValue = true;
                        }
                        $rowData[$importKey] = $cellValue;
                    }

                    if (!$hasValue) {
                        $result['skipped']++;
                        continue;
                    }

                    if ($this->isBlank($rowData['identifier'])) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 識別子がありません。', $row);
                        continue;
                    }

                    $normalizedStatus = null;
                    [$normalizedGroup1, $group1Valid] = $this->normalizeTranslatedValue($rowData['group1'], $allowedGroup1, $group1LabelMap);
                    if (!$group1Valid) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 不正な利用者グループ1 %s', $row, $rowData['group1']);
                        continue;
                    }
                    if ($normalizedGroup1 !== null) {
                        $rowData['group1'] = $normalizedGroup1;
                    }
                    [$normalizedRole, $roleValid] = $this->normalizeTranslatedValue($rowData['role'], $allowedRole, $roleLabelMap);
                    if (!$roleValid) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 不正な権限 %s', $row, $rowData['role']);
                        continue;
                    }
                    if ($normalizedRole !== null) {
                        $rowData['role'] = $normalizedRole;
                    }
                    [$normalizedStatus, $statusValid] = $this->normalizeStatusValue($rowData['status'], $allowedStatus);
                    if (!$statusValid) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 不正な状態 %s', $row, $rowData['status']);
                        continue;
                    }

                    $member = $this->resolveMember($rowData, $seenIdentifiers);

                    if ($member === null) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 識別子がありません。', $row);
                        continue;
                    }

                    if (!$this->isBlank($rowData['identifier'])) {
                        $member->setIdentifier((string) $rowData['identifier']);
                    }
                    if (!$this->isBlank($rowData['full_name'])) {
                        $member->setFullName((string) $rowData['full_name']);
                    }
                    if (!$this->isBlank($rowData['full_name_transcription'])) {
                        $member->setFullNameTranscription((string) $rowData['full_name_transcription']);
                    }
                    if (!$this->isBlank($rowData['group1'])) {
                        $member->setGroup1((string) $rowData['group1']);
                    } else {
                        $member->setGroup1('standard');
                    }
                    if (!$this->isBlank($rowData['group2'])) {
                        $member->setGroup2((string) $rowData['group2']);
                    }
                    if (!$this->isBlank($rowData['communication_address1'])) {
                        $member->setCommunicationAddress1((string) $rowData['communication_address1']);
                    }
                    if (!$this->isBlank($rowData['communication_address2'])) {
                        $member->setCommunicationAddress2((string) $rowData['communication_address2']);
                    }
                    if (!$this->isBlank($rowData['role'])) {
                        $member->setRole((string) $rowData['role']);
                    }
                    if (!$this->isBlank($rowData['status'])) {
                        $member->setStatus($normalizedStatus);
                    }
                    if (!$this->isBlank($rowData['note'])) {
                        $member->setNote((string) $rowData['note']);
                    }
                    if (!$this->isBlank($rowData['expiry_date'])) {
                        try {
                            $member->setExpiryDate(new DateTime((string) $rowData['expiry_date']));
                        } catch (\Exception $e) {
                            $result['errors']++;
                            $result['errorMessages'][] = sprintf('%d行目: 不正な有効期限 %s', $row, $rowData['expiry_date']);
                            continue;
                        }
                    }

                    if ($member->getIdentifier() === null || $member->getFullName() === null) {
                        $result['errors']++;
                        $result['errorMessages'][] = sprintf('%d行目: 識別子とフルネームは必須です。', $row);
                        continue;
                    }

                    $this->entityManager->persist($member);
                    $result['success']++;
                } catch (Throwable $e) {
                    $this->logger->error('Member import row failed', [
                        'row' => $row,
                        'error' => $e->getMessage(),
                    ]);
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 取り込みに失敗しました（%s）', $row, $e->getMessage());
                }
            }

            $this->entityManager->flush();
            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Member import failed', ['error' => $e->getMessage()]);
            $result['errors']++;
            $result['errorMessages'][] = 'ファイルの読み込みに失敗しました: ' . $e->getMessage();
            return $result;
        }
    }

    private function buildHeaderMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $highestColIndex): array
    {
        $headerMap = [];
        $labelMap = MemberFileColumns::getImportHeaderLabelMap($this->t);
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $raw = $sheet->getCell([$col, 1])->getValue();
            if ($raw instanceof RichText) {
                $raw = $raw->getPlainText();
            }
            $label = is_string($raw) ? trim($raw) : (string) $raw;
            if ($label === '') {
                continue;
            }
            if (isset($labelMap[$label])) {
                $headerMap[$col] = $labelMap[$label];
            }
        }
        return $headerMap;
    }

    private function generateFreshRow(): array
    {
        $row = [];
        foreach (MemberFileColumns::getImportKeyList() as $key) {
            $row[$key] = null;
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $rowData
     * @param array<string, Member> $seenIdentifiers
     */
    private function resolveMember(array $rowData, array &$seenIdentifiers): ?Member
    {
        $identifier = $rowData['identifier'] ?? null;
        $identifierKey = null;
        if (!$this->isBlank($identifier)) {
            $identifierKey = trim((string) $identifier);
            if (isset($seenIdentifiers[$identifierKey])) {
                return $seenIdentifiers[$identifierKey];
            }
        }

        if ($identifierKey !== null) {
            $existing = $this->memberRepository->findOneBy(['identifier' => $identifierKey]);
            if ($existing !== null) {
                $this->logger->info('Member already exists: ' . $identifierKey);
                $seenIdentifiers[$identifierKey] = $existing;
                return $existing;
            }
        }

        if ($identifierKey === null) {
            return null;
        }

        $member = new Member();
        $seenIdentifiers[$identifierKey] = $member;
        return $member;
    }

    private function isBlank($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        return false;
    }

    private function getAllowedMemberValues(string $parameter): array
    {
        $values = $this->parameterBag->get($parameter);
        return is_array($values) ? $values : [];
    }

    /**
     * @param string[] $allowedValues
     * @param array<string, string> $labelMap
     * @return array{0: ?string, 1: bool}
     */
    private function normalizeTranslatedValue($value, array $allowedValues, array $labelMap): array
    {
        if ($this->isBlank($value)) {
            return [null, true];
        }
        $trimmed = trim((string) $value);
        if (in_array($trimmed, $allowedValues, true)) {
            return [$trimmed, true];
        }
        if (isset($labelMap[$trimmed])) {
            return [$labelMap[$trimmed], true];
        }
        return [null, false];
    }

    /**
     * @param string[] $allowedStatus
     * @return array{0: ?string, 1: bool}
     */
    private function normalizeStatusValue($value, array $allowedStatus): array
    {
        if ($this->isBlank($value)) {
            return [null, true];
        }
        $normalized = Member::normalizeStatus((string) $value);
        if ($normalized === null || !in_array($normalized, $allowedStatus, true)) {
            return [null, false];
        }
        return [$normalized, true];
    }

    /**
     * @param string[] $allowedValues
     * @return array<string, string>
     */
    private function buildValueLabelMap(array $allowedValues, string $prefix): array
    {
        $map = [];
        foreach ($allowedValues as $value) {
            $key = $prefix . $value;
            $label = $this->t->trans($key);
            if ($label !== $key) {
                $map[$label] = $value;
            }
        }
        return $map;
    }
}
