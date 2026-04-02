<?php

namespace App\Enum;

enum AppointmentSlotStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case FULL = 'FULL';
    case CANCELLED = 'CANCELLED';
}
