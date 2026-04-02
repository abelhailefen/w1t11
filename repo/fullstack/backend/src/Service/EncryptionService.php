<?php

namespace App\Service;

class EncryptionService
{
    public function __construct(private readonly string $licenseEncryptionKey)
    {
        if ($this->licenseEncryptionKey === '') {
            throw new \RuntimeException('LICENSE_ENCRYPTION_KEY is required');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $key = hash('sha256', $this->licenseEncryptionKey, true);
        $cipherRaw = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherRaw === false) {
            throw new \RuntimeException('Failed to encrypt license number');
        }

        return base64_encode($iv . $cipherRaw);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false || strlen($decoded) < 17) {
            throw new \RuntimeException('Invalid encrypted payload');
        }

        $iv = substr($decoded, 0, 16);
        $cipherRaw = substr($decoded, 16);
        $key = hash('sha256', $this->licenseEncryptionKey, true);
        $plaintext = openssl_decrypt($cipherRaw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt license number');
        }

        return $plaintext;
    }

    public function mask(string $plaintext): string
    {
        $tail = strtoupper(substr($plaintext, -4));
        if ($tail === '') {
            $tail = '0000';
        }

        return '***-' . str_pad($tail, 4, '0', STR_PAD_LEFT);
    }
}
