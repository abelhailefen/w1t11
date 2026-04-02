<?php

namespace App\Enum;

enum QuestionStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case OFFLINE = 'OFFLINE';
}
