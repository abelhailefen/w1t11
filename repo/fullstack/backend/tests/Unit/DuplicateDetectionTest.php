<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\QuestionService;
use App\Service\QuestionSimilarityService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DuplicateDetectionTest extends KernelTestCase
{
    public function testSimilarityThresholdBehavior(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(QuestionService::class);
        $sim = $container->get(QuestionSimilarityService::class);
        $user = $container->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'content_admin']);

        $base = $service->create(['content_html' => '<p>abc def ghi jkl mno</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);
        $service->publish((int) $base['id'], $user, true);
        $same = $service->create(['content_html' => '<p>abc def ghi jkl mno</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);
        $near = $service->create(['content_html' => '<p>abc def ghi jkl xyz</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);
        $diff = $service->create(['content_html' => '<p>random unrelated zebra quantum</p>', 'category_id' => 1, 'difficulty' => 3, 'tags' => []], $user);

        $fSame = $sim->checkDuplicates((int) $same['current_version']['id']);
        $fNear = $sim->checkDuplicates((int) $near['current_version']['id']);
        $fDiff = $sim->checkDuplicates((int) $diff['current_version']['id']);

        self::assertNotEmpty($fSame);
        self::assertNotEmpty($fNear);
        self::assertCount(0, $fDiff);
    }
}
