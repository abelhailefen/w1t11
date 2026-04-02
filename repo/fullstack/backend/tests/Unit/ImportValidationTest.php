<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\QuestionImportExportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportValidationTest extends KernelTestCase
{
    public function testPerRowValidation(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(QuestionImportExportService::class);
        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'content_admin']);

        $tmp = tempnam(sys_get_temp_dir(), 'qimp_') . '.csv';
        file_put_contents($tmp, "content,category_id,difficulty\n,1,3\nvalid content,1,0\nvalid content,1,6\nvalid content,1,4\n");
        $file = new UploadedFile($tmp, 'import.csv', 'text/csv', null, true);

        $result = $service->importFromFile($file, $user);
        $statuses = array_column($result['results'], 'status');
        self::assertSame('error', $statuses[0]);
        self::assertSame('error', $statuses[1]);
        self::assertSame('error', $statuses[2]);
        self::assertSame('success', $statuses[3]);
    }
}
