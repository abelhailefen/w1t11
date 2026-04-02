<?php

namespace App\Enum;

enum QuestionSimilarityStatus: string
{
    case PENDING = 'pending';
    case DISMISSED = 'dismissed';
    case ACCEPTED = 'accepted';
}
