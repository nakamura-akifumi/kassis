<?php

namespace App\Controller;

use App\Form\AmazonImportFormType;
use App\Service\AmazonImportService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AmazonImportController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/file/import_amazon_orders', name: 'app_import_amazon_orders')]
    public function importAmazon(Request $request, AmazonImportService $amazonImportService): Response
    {
        $this->logger->info('Amazonインポート画面にアクセスがありました', [
            'ip' => $request->getClientIp(),
            'user' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous'
        ]);
        
        $form = $this->createForm(AmazonImportFormType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $zipFile = $form->get('zipFile')->getData();
            $kindleFile = $form->get('kindleFile')->getData();
            $onlyIsbnAsin = (bool) $form->get('onlyIsbnAsin')->getData();
            
            if ($zipFile) {
                $this->logger->info('ファイルがアップロードされました', [
                    'filename' => $zipFile->getClientOriginalName(),
                    'size' => $zipFile->getSize(),
                    'mimeType' => $zipFile->getMimeType(),
                    'kindleFilename' => $kindleFile?->getClientOriginalName(),
                    'onlyIsbnAsin' => $onlyIsbnAsin,
                ]);
                
                try {
                    $result = $amazonImportService->processFile($zipFile, $onlyIsbnAsin, $kindleFile);
                    
                    $this->logger->info('インポート処理が完了しました', [
                        'success' => $result['success'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors']
                    ]);
                    
                    if ($result['success'] > 0) {
                        $this->addFlash(
                            'success',
                            sprintf(
                                '%d件のデータを正常にインポートしました。(スキップ: %d件, エラー: %d件)',
                                $result['success'],
                                $result['skipped'],
                                $result['errors']
                            )
                        );
                    } elseif ($result['errors'] > 0) {
                        $this->logger->error('インポート中にエラーが発生しました', [
                            'errorMessages' => $result['errorMessages']
                        ]);
                        
                        $this->addFlash(
                            'danger',
                            'インポート中にエラーが発生しました。詳細は以下を参照してください。'
                        );
                        
                        foreach ($result['errorMessages'] as $message) {
                            $this->addFlash('danger', $message);
                        }
                    } else {
                        $this->logger->warning('インポートするデータがありませんでした', [
                            'skipped' => $result['skipped']
                        ]);
                        
                        $this->addFlash(
                            'warning',
                            sprintf('インポートするデータがありませんでした。(スキップ: %d件)', $result['skipped'])
                        );
                    }
                } catch (\Exception $e) {
                    $this->logger->critical('予期せぬエラーが発生しました', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $this->addFlash(
                        'danger',
                        '処理中に予期せぬエラーが発生しました: ' . $e->getMessage()
                    );
                }
            } else {
                $this->logger->warning('ファイルがアップロードされていません');
                $this->addFlash('warning', 'ファイルを選択してください。');
            }
        } elseif ($form->isSubmitted()) {
            $this->logger->warning('フォームバリデーションエラー', [
                'errors' => $form->getErrors(true, true)
            ]);

            $this->addFlash('danger', '入力内容にエラーがあります。内容を確認してください。');
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->render('import/amazon_orders.html.twig', [
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }
}
