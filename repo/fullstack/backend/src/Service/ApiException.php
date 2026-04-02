<?php

namespace App\Service;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpCode,
        private readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
