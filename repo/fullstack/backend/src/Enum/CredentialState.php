<?php

namespace App\Enum;

enum CredentialState: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case RESUBMISSION_REQUESTED = 'RESUBMISSION_REQUESTED';
}
