<?php

namespace App\Service;

use App\Entity\Code;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CodeFileService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param Code[] $codes
     */
    public function generateExportFile(array $codes, string $format): string
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
        foreach ($codes as $code) {
            $colIndex = 1;
            foreach ($columns as $column) {
                $getter = $column['getter'];
                $sheet->setCellValue([$colIndex, $row], $code->$getter());
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

        $tempFile = tempnam(sys_get_temp_dir(), 'code_export_');
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
     * @param string[] $typeOptions
     * @return array{created:int, updated:int, skipped:int, errors:int, errorMessages: string[]}
     */
    public function importCodesFromFile(UploadedFile $file, array $typeOptions = []): array
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

            if ($colMap['type'] === null || $colMap['identifier'] === null || $colMap['value'] === null) {
                $result['errors']++;
                $result['errorMessages'][] = 'ヘッダーに「種別」「識別子」「値」が見つかりません。エクスポートしたファイルを使用してください。';
                return $result;
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

                if (strlen($value) > 32) {
                    $result['errors']++;
                    $result['errorMessages'][] = sprintf('%d行目: 値は32文字以内で入力してください。', $i);
                    continue;
                }

                $code = $this->entityManager->getRepository(Code::class)->findOneBy([
                    'type' => $type,
                    'identifier' => $identifier,
                ]);

                if ($code === null) {
                    $code = new Code();
                    $code->setType($type);
                    $code->setIdentifier($identifier);
                    $result['created']++;
                    $this->entityManager->persist($code);
                } else {
                    $result['updated']++;
                }

                $code->setValue($value);
                $code->setDisplayname($displayname !== null && $displayname !== '' ? $displayname : null);
                $code->setDisplayOrder($displayOrder);
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
     * @return array<int, array{label: string, getter: string}>
     */
    private function getExportColumns(): array
    {
        return [
            ['label' => '種別', 'getter' => 'getType'],
            ['label' => '識別子', 'getter' => 'getIdentifier'],
            ['label' => '値', 'getter' => 'getValue'],
            ['label' => '表示名', 'getter' => 'getDisplayname'],
            ['label' => '表示順', 'getter' => 'getDisplayOrder'],
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
}
