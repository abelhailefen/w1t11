<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadTest extends WebTestCase
{
    public function testFileUploadValidationAndDownload(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], content: json_encode([
            'full_name' => 'Upload Tester',
            'firm_id' => 1,
            'license_number' => 'TX-678900',
            'license_jurisdiction' => 'TX',
        ], JSON_THROW_ON_ERROR));
        $practitioner = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($pdfPath, "%PDF-1.4\n%EOF");
        $pdf = new UploadedFile($pdfPath, 'doc.pdf', 'application/pdf', null, true);

        $client->request('POST', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], files: ['file' => $pdf]);
        self::assertResponseStatusCodeSame(201);
        $uploaded = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $bigPath = tempnam(sys_get_temp_dir(), 'big_');
        file_put_contents($bigPath, str_repeat('A', 15 * 1024 * 1024));
        $big = new UploadedFile($bigPath, 'big.pdf', 'application/pdf', null, true);
        $client->request('POST', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], files: ['file' => $big]);
        self::assertResponseStatusCodeSame(413);

        $exePath = tempnam(sys_get_temp_dir(), 'exe_');
        file_put_contents($exePath, 'MZ');
        $exe = new UploadedFile($exePath, 'malware.exe', 'application/x-msdownload', null, true);
        $client->request('POST', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], files: ['file' => $exe]);
        self::assertResponseStatusCodeSame(400);

        $client->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/' . $uploaded['id'] . '/download', server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $data['token'];
    }
}
