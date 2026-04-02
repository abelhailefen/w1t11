<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class CaptchaService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function generateChallenge(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $question = sprintf('What is %d + %d?', $a, $b);
        $answer = (string) ($a + $b);
        $token = bin2hex(random_bytes(16));

        $image = imagecreatetruecolor(220, 60);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $fg = imagecolorallocate($image, 35, 35, 35);
        imagefilledrectangle($image, 0, 0, 220, 60, $bg);
        imagestring($image, 5, 12, 22, $question, $fg);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $this->connection->insert('captcha_challenges', [
            'token' => $token,
            'challenge_payload' => json_encode(['question' => $question, 'answer' => $answer], JSON_THROW_ON_ERROR),
            'expected_answer_hash' => password_hash($answer, PASSWORD_BCRYPT, ['cost' => 12]),
            'expires_at' => (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s'),
            'used_at' => null,
        ]);

        return [
            'image' => base64_encode($png),
            'token' => $token,
        ];
    }

    public function verify(string $token, string $answer): bool
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM captcha_challenges WHERE token = :token', ['token' => $token]);
        if (!$row) {
            return false;
        }

        if (!empty($row['used_at'])) {
            return false;
        }

        $expiresAt = new \DateTimeImmutable((string) $row['expires_at']);
        if ($expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        $valid = password_verify(trim($answer), (string) $row['expected_answer_hash']);
        if ($valid) {
            $this->connection->update('captcha_challenges', [
                'used_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], ['id' => $row['id']]);
        }

        return $valid;
    }
}
