<?php

namespace App\Command;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CommandFileHelperTrait
{
    protected function buildUploadedFile(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path), null, null, true);
    }

    protected function resolveOutputPath(string $output, string $defaultName): string
    {
        if (is_dir($output)) {
            return rtrim($output, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $defaultName;
        }

        return $output;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('出力先ディレクトリの作成に失敗しました。');
        }
    }

    protected function writeTempFile(string $tempFile, string $outputPath): void
    {
        $this->ensureDirectoryExists($outputPath);
        if (!copy($tempFile, $outputPath)) {
            throw new RuntimeException('出力ファイルの保存に失敗しました。');
        }
    }
}
