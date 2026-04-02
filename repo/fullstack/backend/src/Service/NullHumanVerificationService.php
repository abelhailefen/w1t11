<?php

namespace App\Service;

class NullHumanVerificationService implements HumanVerificationInterface
{
    public function __construct(private readonly bool $enabled)
    {
    }

    public function verify(array $request): bool
    {
        if (!$this->enabled) {
            return true;
        }

        return true;
    }
}
