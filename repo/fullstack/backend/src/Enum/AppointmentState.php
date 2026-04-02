<?php

namespace App\Enum;

enum AppointmentState: string
{
    case HELD = 'HELD';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case RESCHEDULED = 'RESCHEDULED';
}
