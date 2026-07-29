<?php

namespace App\Enums;

enum DomainVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
