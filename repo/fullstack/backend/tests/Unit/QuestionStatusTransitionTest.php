<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\ApiException;
use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QuestionStatusTransitionTest extends KernelTestCase
{
    public function testAllowedAndRejectedTransitions(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(QuestionService::class);
        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'content_admin']);
        $q = $service->create(['content_html' => '<p>status transition case</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);

        $service->changeStatus((int) $q['id'], 'PUBLISHED');
        $service->changeStatus((int) $q['id'], 'OFFLINE');

        $q2 = $service->create(['content_html' => '<p>status invalid case</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);
        $this->expectException(ApiException::class);
        $service->changeStatus((int) $q2['id'], 'OFFLINE');
    }
}
