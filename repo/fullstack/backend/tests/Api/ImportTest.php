<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class ImportTest extends ApiWebTestCase
{
    public function testImportValidAndInvalidCsv(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'content_admin', 'Content@123');

        $tmp = tempnam(sys_get_temp_dir(), 'qapi_') . '.csv';
        file_put_contents($tmp, "content,category_id,difficulty\nvalid row,1,3\ninvalid difficulty,1,9\n");

        $client->request('POST', '/api/v1/questions/import', server: ['HTTP_Authorization' => 'Bearer ' . $token], files: [
            'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($tmp, 'import.csv', 'text/csv', null, true),
        ]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $data['results']);
        self::assertSame('success', $data['results'][0]['status']);
        self::assertSame('error', $data['results'][1]['status']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
