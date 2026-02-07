<?php

namespace App\Service;

use App\Entity\Manifestation;
use App\Entity\ManifestationAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class AmazonImportService
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $managerRegistry;
    private string $projectDir;
    private LoggerInterface $logger;
    protected NdlImportService $ndlImportService;
    private bool $useNdl;
    private SluggerInterface $slugger;


    public function __construct(
        NdlImportService $ndlImportService,
        EntityManagerInterface $entityManager,
        ManagerRegistry $managerRegistry,
        LoggerInterface $logger,
        string $projectDir,
        bool $useNdl,
        SluggerInterface $slugger
    ) {
        $this->entityManager = $entityManager;
        $this->managerRegistry = $managerRegistry;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->ndlImportService = $ndlImportService;
        $this->useNdl = $useNdl;
        $this->slugger = $slugger;
    }

    public function processFile(UploadedFile $zipFile, bool $onlyIsbnAsin = false, ?UploadedFile $kindleFile = null): array
    {
        $result = [
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errorMessages' => []
        ];

        $this->logger->info('Amazon購入履歴のインポートを開始します', [
            'filename' => $zipFile->getClientOriginalName(),
            'size' => $zipFile->getSize(),
            'onlyIsbnAsin' => $onlyIsbnAsin,
            'kindleFilename' => $kindleFile?->getClientOriginalName(),
        ]);

        // 一時ディレクトリを作成
        $tempDir = $this->projectDir . '/var/temp/' . 'amazon_import_' . Uuid::v7()->toHex();
        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }

        // ZIPファイルを一時ディレクトリに保存
        $zipPath = $tempDir . '/' . $zipFile->getClientOriginalName();
        $zipFile->move(dirname($zipPath), basename($zipPath));
        $this->logger->info('ZIPファイルをテンポラリディレクトリに保存しました', ['path' => $zipPath]);

        $this->logger->info("tempDir=".$tempDir." zipPath=".$zipPath);

        try {
            // ZIPファイルを解凍
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                $this->logger->info('ZIPファイルを解凍しました', ['tempDir' => $tempDir]);

                // CSVファイルを再帰的に探す
                $csvFiles = $this->findCsvFilesRecursively($tempDir);
                if (empty($csvFiles)) {
                    $this->logger->error('ZIPファイル内にCSVファイルが見つかりませんでした');
                    $result['errors']++;
                    $result['errorMessages'][] = 'ZIPファイル内にCSVファイルが見つかりませんでした。';
                    return $result;
                }

                $this->logger->info('CSVファイルが見つかりました', ['files' => $csvFiles]);

                $kindleItemsByAsin = $this->loadKindleItems($kindleFile, $result);

                // すべてのCSVファイルを処理する
                foreach ($csvFiles as $csvFile) {
                    $this->logger->info('CSVファイルの処理を開始します', ['file' => $csvFile]);
                    $fileResult = $this->processAmazonCsv($csvFile, [
                        'success' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                        'errorMessages' => []
                    ], $onlyIsbnAsin, $kindleItemsByAsin);

                    // 結果を集計する
                    $result['success'] += $fileResult['success'];
                    $result['skipped'] += $fileResult['skipped'];
                    $result['errors'] += $fileResult['errors'];
                    $result['errorMessages'] = array_merge(
                        $result['errorMessages'],
                        $fileResult['errorMessages']
                    );
                    $result['processedFiles'][] = [
                        'file' => basename($csvFile),
                        'success' => $fileResult['success'],
                        'skipped' => $fileResult['skipped'],
                        'errors' => $fileResult['errors']
                    ];

                    $this->logger->info('CSVファイルの処理が完了しました', [
                        'file' => $csvFile,
                        'success' => $fileResult['success'],
                        'skipped' => $fileResult['skipped'],
                        'errors' => $fileResult['errors']
                    ]);
                }
            } else {
                $this->logger->error('ZIPファイルを開くことができませんでした', ['path' => $zipPath]);
                $result['errors']++;
                $result['errorMessages'][] = 'ZIPファイルを開くことができませんでした。';
            }
        } catch (\Exception $e) {
            $this->logger->error('エラーが発生しました', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $result['errors']++;
            $result['errorMessages'][] = 'エラーが発生しました: ' . $e->getMessage();
        } finally {
            // 一時ディレクトリを削除
            $this->removeDirectory($tempDir);
            $this->logger->info('テンポラリディレクトリを削除しました', ['tempDir' => $tempDir]);
        }

        $this->logger->info('Amazon購入履歴のインポートが完了しました', [
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ]);

        return $result;
    }

    private function findCsvFilesRecursively(string $directory): array
    {
        $csvFiles = [];
        $this->logger->debug('CSVファイルを再帰的に検索します', ['directory' => $directory]);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $needFiles = array('Digital Items.csv','Retail.OrderHistory.1.csv','Retail.OrderHistory.3.csv');
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'csv') {
                    if (in_array($file->getFilename(), $needFiles)) {
                        $csvFiles[] = $file->getPathname();
                        $this->logger->debug('CSVファイルを見つけました', ['path' => $file->getPathname()]);
                    }
                }
            }

            $this->logger->info('再帰的検索の結果、CSVファイルが' . count($csvFiles) . '件見つかりました');
        } catch (\Exception $e) {
            $this->logger->error('CSVファイルの再帰的検索中にエラーが発生しました', [
                'error' => $e->getMessage(),
                'directory' => $directory
            ]);
        }

        return $csvFiles;
    }


    private function processAmazonCsv(string $csvPath, array $result, bool $onlyIsbnAsin, array $kindleItemsByAsin): array
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            $this->logger->error('CSVファイルを開くことができませんでした', ['path' => $csvPath]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルを開くことができませんでした。';
            return $result;
        }

        // ヘッダー行を読み込む
        // TODO: fgetcsvのパラメータを考慮する
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->logger->error('CSVファイルの形式が正しくありません', ['path' => $csvPath]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルの形式が正しくありません。';
            fclose($handle);
            return $result;
        }

        // BOM（Byte Order Mark）の処理
        // UTF-8のBOMは "\xEF\xBB\xBF" で、最初のカラム名の先頭についている可能性がある
        if (!empty($headers[0])) {
            // BOMを検出して削除
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            // 先頭に残ったダブルクォートや制御文字をトリムする
            $headers[0] = trim($headers[0], "\x00..\x1F\x7F\"");
        }

        $this->logger->info('CSVヘッダーを読み込みました', ['headers' => $headers]);

        $purchaseType = "";
        $needDigitalFiles = array('Digital Items.csv');
        $needRetailFiles = array('Retail.OrderHistory.1.csv','Retail.OrderHistory.3.csv');
        $csvFilesWithoutPath = pathinfo($csvPath, PATHINFO_BASENAME);

        // Amazon CSVのヘッダーを確認
        if (in_array($csvFilesWithoutPath, $needDigitalFiles)) {
            // KINDLE
            $materialType = "Digital";
            $titleIndex = array_search('ProductName', $headers);
            $asinIndex = array_search('ASIN', $headers);
            $buyerIndex = array_search('Marketplace', $headers);
            $orderDateIndex = array_search('OrderDate', $headers);
            $orderIdIndex = array_search('Order ID', $headers);
            $priceIndex = array_search('Total Owed', $headers);
            $currencyIndex = array_search('Currency', $headers);
            $contributor2Index = array_search('SellerOfRecord', $headers);
        } elseif (in_array($csvFilesWithoutPath, $needRetailFiles)) {
            // Retail
            $materialType = "Retail";
            $titleIndex = array_search('Product Name', $headers);
            $asinIndex = array_search('ASIN', $headers);
            $buyerIndex = array_search('Website', $headers);
            $orderDateIndex = array_search('Order Date', $headers);
            $orderIdIndex = array_search('Order ID', $headers);
            $priceIndex = array_search('Total Owed', $headers);
            $currencyIndex = array_search('Currency', $headers);
            $contributor2Index = null;

            $this->logger->debug('@1 Retail headers:'.implode(',', $headers));
            $this->logger->debug('@1 Retail buyerIndex:'.$buyerIndex);

        } else {
            $this->logger->debug('処理対象外のファイルです。'.$csvFilesWithoutPath);
            return $result;
        }

        if ($titleIndex === false || $asinIndex === false) {
            // Amazon CSVのヘッダーに必要なカラムが存在しない
            $this->logger->error('CSVファイルに必要なカラムが見つかりませんでした', [
                'requiredColumns' => ['Title', 'ASIN'],
                'foundColumns' => $headers
            ]);
            $result['errors']++;
            $result['errorMessages'][] = 'CSVファイルに必要なカラム（Title、ASIN）が見つかりませんでした。';
            fclose($handle);
            return $result;
        }

        // CSVファイルを行ごとに処理
        $rowCount = 0;
        $batchSize = 20;

        while (($data = fgetcsv($handle)) !== false) {
            $rowCount++;

            // EntityManagerが閉じている場合は再取得
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $this->managerRegistry->getManager();
            }

            $repository = $this->entityManager->getRepository(Manifestation::class);

            try {
                // すでに同じ購入識別子が存在するか確認
                $buyerIdentifier = $data[$orderIdIndex]."_".$data[$asinIndex];
                $existingItem = $repository->findOneBy(['buyer_identifier' => $buyerIdentifier]);

                if ($existingItem) {
                    $this->logger->debug('同一購入識別子が既に存在するためスキップします', [
                        'buyer_identifier' => $buyerIdentifier
                    ]);
                    $result['skipped']++;
                    continue;
                }

                $title = $data[$titleIndex] ?? null;
                if (empty($title)) {
                    $this->logger->debug('タイトルが空のため行をスキップします', ['row' => $rowCount]);
                    $result['skipped']++;
                    continue;
                }
                if ($title === "Not Applicable") {
                    $this->logger->debug('タイトルが Not Applicable のため行をスキップします', ['row' => $rowCount]);
                    $result['skipped']++;
                    continue;
                }

                $buyer = null;
                if ($buyerIndex !== false && !empty($data[$buyerIndex])) {
                    $buyer = $data[$buyerIndex];
                }
                
                // ASIN
                $identifier = null;
                $externalIdentifier3 = null;
                if ($asinIndex !== false && !empty($data[$asinIndex])) {
                    $identifier = $data[$asinIndex];
                    $externalIdentifier3 = $data[$asinIndex];
                }
                $kindleItem = null;
                if ($externalIdentifier3 !== null && isset($kindleItemsByAsin[$externalIdentifier3])) {
                    $kindleItem = $kindleItemsByAsin[$externalIdentifier3];
                }

                // ISBN
                $externalIdentifier1 = null;
                if ($externalIdentifier3 !== null) {
                    // ISBNであれば$externalIdentifier1に設定 (ISBN-10 または ISBN-13)
                    if (IsbnService::isValidIsbn($externalIdentifier3)) {
                        $externalIdentifier1 = IsbnService::convertToIsbn13($externalIdentifier3);
                        // 識別子にも同じものを設定
                        // TODO: 同じものを設定するかは別途検討（モードで変更できる、別の発注なので同じものを注文したかも等）
                        $identifier = $externalIdentifier1;
                    }
                }

                if ($onlyIsbnAsin && $externalIdentifier1 === null && $kindleItem === null) {
                    $this->logger->debug('ASINがISBNとして妥当ではないためスキップします', [
                        'row' => $rowCount,
                        'asin' => $externalIdentifier3,
                        'file' => $csvFilesWithoutPath,
                    ]);
                    $result['skipped']++;
                    continue;
                }

                // 同じIdentifierが存在するか確認
                $existingItemByIdentifier = $repository->findOneBy(['identifier' => $identifier]);

                if ($existingItemByIdentifier) {
                    $oldIdentifier = $identifier;
                    $identifier = $data[$asinIndex]."-amz_".Uuid::v7()->toHex();
                    $this->logger->debug('同一識別子(identifier)が既に存在するため別の識別子に変更します', [
                        'identifier' => $oldIdentifier,
                        'new_identifier' => $identifier,
                        'title' => $title
                    ]);

                }

                // 購入日の処理
                $purchaseDate = null;
                if ($orderDateIndex !== false && !empty($data[$orderDateIndex])) {
                    try {
                        $purchaseDate = new \DateTime($data[$orderDateIndex]);
                    } catch (\Exception $e) {
                        $this->logger->warning('日付の解析に失敗しました', [
                            'date' => $data[$orderDateIndex],
                            'error' => $e->getMessage()
                        ]);
                        // 日付の解析に失敗した場合はスキップ
                    }
                }

                // NDLからのインポート処理
                $manifestation = null;
                if ($this->useNdl && $externalIdentifier1 !== null) {
                    try {
                        $this->logger->info('NDLからのデータ取得を試行します', [
                            'isbn' => $externalIdentifier1,
                            'title' => $title
                        ]);

                        $manifestation = $this->ndlImportService->createManifestationByIsbn($externalIdentifier1);

                    } catch (\Exception $e) {
                        $this->logger->warning('NDLからのデータ取得中にエラーが発生しました', [
                            'isbn' => $externalIdentifier1,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                //
                $price = $data[$priceIndex];
                $priceCurrency = null;
                if ($currencyIndex !== false && !empty($data[$currencyIndex])) {
                    $priceCurrency = $data[$currencyIndex];
                }
                $contributor2 = null;
                if ($contributor2Index !== false && !empty($data[$contributor2Index])) {
                    $contributor2 = $data[$contributor2Index];
                }

                // Manifestationエンティティを作成
                if ($manifestation === null) {
                    $manifestation = new Manifestation();
                }
                $manifestation->setTitle($title);
                $manifestation->setIdentifier($identifier);
                $manifestation->setBuyer($buyer);
                $manifestation->setBuyerIdentifier($buyerIdentifier);
                $manifestation->setRecordSource('Amazon購入履歴:' . $csvFilesWithoutPath);
                $manifestation->setExternalIdentifier1($externalIdentifier1);
                $manifestation->setExternalIdentifier3($externalIdentifier3);
                if ($purchaseDate) {
                    $manifestation->setPurchaseDate($purchaseDate);
                }
                $manifestation->setPrice($price);
                $manifestation->setPriceCurrency($priceCurrency);
                $manifestation->setStatus1('New');
                if ($contributor2 !== null) {
                    $manifestation->setContributor2($contributor2);
                }
                if ($materialType === 'Digital') {
                    $manifestation->setType2("デジタル");
                }
                if ($materialType === 'Retail' && $manifestation->getType1() === null) {
                    $manifestation->setType1("リテイル");
                }
                if ($kindleItem !== null) {
                    $manifestation->setType2("kindle");
                }

                // データベースに保存
                $this->entityManager->persist($manifestation);

                // バッチサイズごとにフラッシュするか、エラー復旧を容易にするため逐次処理を維持
                // 今回はEntityManagerClosed対策を優先し、逐次flushしつつ、失敗時にリセットする構成にする
                $this->entityManager->flush();
                
                $this->logger->info('新しい情報を登録しました', [
                    'id' => $manifestation->getId(),
                    'title' => $title,
                    'identifier' => $identifier
                ]);
                
                if ($kindleItem !== null && !empty($kindleItem['productImage'])) {
                    $this->createAttachmentFromImageUrl($manifestation, $kindleItem['productImage']);
                }

                $result['success']++;

                // メモリ節約のため定期的にクリア
                if (($rowCount % $batchSize) === 0) {
                    $this->entityManager->clear();
                }

            } catch (\Exception $e) {
                $this->logger->error('行の処理中にエラーが発生しました', [
                    'row' => $rowCount,
                    'error' => $e->getMessage(),
                ]);
                $result['errors']++;
                $result['errorMessages'][] = '行の処理中にエラーが発生しました: ' . $e->getMessage();

                // 重要：エラーが発生した場合は現在のトランザクションを破棄し、
                // 次の行の処理のためにEntityManagerをリセットする必要がある
                if (!$this->entityManager->isOpen()) {
                    $this->managerRegistry->resetManager();
                    $this->entityManager = $this->managerRegistry->getManager();
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->logger->info('CSVファイルの処理が完了しました', [
            'path' => $csvPath,
            'rowsProcessed' => $rowCount,
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ]);

        fclose($handle);
        return $result;
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== "." && $object !== "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    private function loadKindleItems(?UploadedFile $kindleFile, array &$result): array
    {
        if ($kindleFile === null) {
            return [];
        }

        $kindlePath = $kindleFile->getPathname();
        $contents = @file_get_contents($kindlePath);
        if ($contents === false) {
            $this->logger->error('kindle.jsonの読み込みに失敗しました', ['path' => $kindlePath]);
            $result['errors']++;
            $result['errorMessages'][] = 'kindle.jsonの読み込みに失敗しました。';
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            $this->logger->error('kindle.jsonの形式が正しくありません', [
                'path' => $kindlePath,
                'error' => json_last_error_msg(),
            ]);
            $result['errors']++;
            $result['errorMessages'][] = 'kindle.jsonの形式が正しくありません。';
            return [];
        }

        $itemsByAsin = [];
        foreach ($decoded as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $asin = isset($item['asin']) ? trim((string) $item['asin']) : '';
            if ($asin === '') {
                continue;
            }
            if (!isset($itemsByAsin[$asin])) {
                $itemsByAsin[$asin] = $item;
            }
        }

        $this->logger->info('kindle.jsonの読み込みが完了しました', [
            'items' => count($itemsByAsin),
        ]);

        return $itemsByAsin;
    }

    private function createAttachmentFromImageUrl(Manifestation $manifestation, string $imageUrl): void
    {
        try {
            $httpClient = HttpClient::create();
            $response = $httpClient->request('GET', $imageUrl);
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('画像取得に失敗しました', [
                    'status' => $statusCode,
                    'url' => $imageUrl,
                ]);
                return;
            }

            $headers = $response->getHeaders(false);
            $contentType = $headers['content-type'][0] ?? null;
            $content = $response->getContent(false);
            if ($content === '') {
                $this->logger->warning('画像データが空でした', ['url' => $imageUrl]);
                return;
            }

            $extension = $this->guessImageExtension($contentType, $imageUrl);
            $baseTitle = $manifestation->getTitle() ?: 'kindle-image';
            $safeFilename = $this->slugger->slug($baseTitle)->lower();
            if ($safeFilename === '') {
                $safeFilename = 'kindle-image';
            }
            $newFilename = $safeFilename . '-' . Uuid::v7()->toHex() . '.' . $extension;

            $uploadDir = $this->projectDir . '/public/uploads/attachments';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                $this->logger->error('添付ファイルディレクトリの作成に失敗しました', ['dir' => $uploadDir]);
                return;
            }

            $filePath = $uploadDir . '/' . $newFilename;
            if (@file_put_contents($filePath, $content) === false) {
                $this->logger->error('画像の保存に失敗しました', ['path' => $filePath]);
                return;
            }

            $originalName = basename((string) parse_url($imageUrl, PHP_URL_PATH));
            if ($originalName === '') {
                $originalName = $newFilename;
            }

            $attachment = new ManifestationAttachment();
            $attachment->setManifestation($manifestation);
            $attachment->setFileName($originalName);
            $attachment->setFilePath('uploads/attachments/' . $newFilename);
            $attachment->setFileSize(strlen($content));
            $attachment->setMimeType($contentType);
            $attachment->setSourceUrl($imageUrl);

            $this->entityManager->persist($attachment);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('画像添付の作成中にエラーが発生しました', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
            ]);
        }
    }

    private function guessImageExtension(?string $mimeType, string $imageUrl): string
    {
        $normalized = strtolower((string) $mimeType);
        $normalized = trim(explode(';', $normalized)[0]);
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
        ];
        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        $path = (string) parse_url($imageUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '') {
            return $ext;
        }

        return 'jpg';
    }
}
