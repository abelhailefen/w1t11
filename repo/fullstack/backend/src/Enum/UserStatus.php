<?php

namespace App\Enum;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case LOCKED = 'LOCKED';
    case DISABLED = 'DISABLED';
}
