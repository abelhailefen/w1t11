<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private const MAX_SIZE_BYTES = 10485760;
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function __construct(private readonly string $uploadBaseDir)
    {
    }

    public function storeCredentialFile(UploadedFile $file, int $practitionerId): array
    {
        $uploadError = $file->getError();
        if ($uploadError !== UPLOAD_ERR_OK) {
            $status = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
            throw new ApiException('File upload failed', $status, ['upload_error' => $uploadError]);
        }

        $size = $file->getSize();
        if ($size === null && $file->getPathname() !== '') {
            $size = filesize($file->getPathname()) ?: 0;
        }
        $size ??= 0;
        if ($size > self::MAX_SIZE_BYTES) {
            throw new ApiException('File too large. Maximum is 10MB', 413);
        }

        if ($file->getPathname() === '' || !is_file($file->getPathname())) {
            throw new ApiException('Uploaded file is unavailable', 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string) finfo_file($finfo, $file->getPathname()) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($detectedMime === '' || $detectedMime === 'application/octet-stream') {
            $detectedMime = (string) $file->getClientMimeType();
        }
        if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new ApiException('Unsupported file type', 400, ['allowed' => self::ALLOWED_MIME_TYPES]);
        }

        $targetDir = rtrim($this->uploadBaseDir, '/\\') . DIRECTORY_SEPARATOR . $practitionerId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0770, true) && !is_dir($targetDir)) {
            throw new ApiException('Unable to create upload directory', 500);
        }

        $extension = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = match ($detectedMime) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                default => 'bin',
            };
        }
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $storedFile = $file->move($targetDir, $safeFilename);

        return [
            'storage_path' => $storedFile->getPathname(),
            'mime_type' => $detectedMime,
            'size_bytes' => $size,
            'original_name' => $file->getClientOriginalName(),
        ];
    }
}
