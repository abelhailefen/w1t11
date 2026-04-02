<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\FileUploadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadValidationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/regops_upload_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function testValidPdfAccepted(): void
    {
        $service = new FileUploadService($this->tempDir);
        $file = $this->makeFile('sample.pdf', "%PDF-1.4\n%EOF", 'application/pdf');
        $result = $service->storeCredentialFile($file, 1);
        self::assertSame('application/pdf', $result['mime_type']);
    }

    public function testValidJpgAccepted(): void
    {
        $service = new FileUploadService($this->tempDir);
        $file = $this->makeFile('sample.jpg', "\xFF\xD8\xFF\xE0", 'image/jpeg');
        $result = $service->storeCredentialFile($file, 1);
        self::assertSame('image/jpeg', $result['mime_type']);
    }

    public function testValidPngAccepted(): void
    {
        $service = new FileUploadService($this->tempDir);
        $file = $this->makeFile('sample.png', "\x89PNG\r\n\x1A\n", 'image/png');
        $result = $service->storeCredentialFile($file, 1);
        self::assertSame('image/png', $result['mime_type']);
    }

    public function testInvalidMimeRejected(): void
    {
        $this->expectException(ApiException::class);
        $service = new FileUploadService($this->tempDir);
        $file = $this->makeFile('malware.exe', 'MZ....', 'application/x-msdownload');
        $service->storeCredentialFile($file, 1);
    }

    public function testOversizeRejected(): void
    {
        $this->expectException(ApiException::class);
        $service = new FileUploadService($this->tempDir);
        $path = $this->tempDir . '/oversize.pdf';
        file_put_contents($path, str_repeat('A', 11 * 1024 * 1024));
        $file = new UploadedFile($path, 'oversize.pdf', 'application/pdf', null, true);
        $service->storeCredentialFile($file, 1);
    }

    private function makeFile(string $name, string $content, string $mime): UploadedFile
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($path);
    }
}
