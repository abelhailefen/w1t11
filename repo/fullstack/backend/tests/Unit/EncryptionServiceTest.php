<?php

namespace App\Tests\Unit;

use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    public function testEncryptDecryptRoundtrip(): void
    {
        $service = new EncryptionService('0123456789abcdef0123456789abcdef');
        $plaintext = 'NY-123456';
        $ciphertext = $service->encrypt($plaintext);

        self::assertNotSame($plaintext, $ciphertext);
        self::assertSame($plaintext, $service->decrypt($ciphertext));
    }

    public function testMaskFormat(): void
    {
        $service = new EncryptionService('0123456789abcdef0123456789abcdef');
        self::assertSame('***-3456', $service->mask('NY-123456'));
    }

    public function testDifferentCiphertextForSameInput(): void
    {
        $service = new EncryptionService('0123456789abcdef0123456789abcdef');
        $a = $service->encrypt('VALUE-1');
        $b = $service->encrypt('VALUE-1');
        self::assertNotSame($a, $b);
    }

    public function testEmptyKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        new EncryptionService('');
    }
}
