<?php

namespace App\Service;

use App\Entity\Member;
use App\Repository\MemberRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MemberFileService
{
    public function __construct(
        private TranslatorInterface $t,
        private EntityManagerInterface $entityManager,
        private MemberRepository $memberRepository,
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

            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $rowData = $this->generateFreshRow();
                    $hasValue = false;
                    foreach ($headerMap as $colIndex => $importKey) {
                        $cellValue = $sheet->getCell([$colIndex, $row])->getValue();
                        if (!$this->isBlank($cellValue)) {
                            $hasValue = true;
                        }
                        $rowData[$importKey] = $cellValue;
                    }

                    if (!$hasValue) {
                        $result['skipped']++;
                        continue;
                    }

                    $member = $this->resolveMember($rowData);

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
                        $normalizedStatus = Member::normalizeStatus((string) $rowData['status']);
                        if ($normalizedStatus === null) {
                            $result['errors']++;
                            $result['errorMessages'][] = sprintf('%d行目: 不正な状態 %s', $row, $rowData['status']);
                            continue;
                        }
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

    private function resolveMember(array $rowData): ?Member
    {
        $id = $rowData['id'] ?? null;
        $identifier = $rowData['identifier'] ?? null;

        if (!$this->isBlank($id)) {
            $member = $this->memberRepository->find((int) $id);
            if ($member !== null) {
                return $member;
            }
        }

        if (!$this->isBlank($identifier)) {
            $existing = $this->memberRepository->findOneBy(['identifier' => (string) $identifier]);
            if ($existing !== null) {
                return $existing;
            }
        }

        if ($this->isBlank($identifier)) {
            return null;
        }

        return new Member();
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
}
