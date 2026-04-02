<?php

namespace App\Service;

interface HumanVerificationInterface
{
    public function verify(array $request): bool;
}
