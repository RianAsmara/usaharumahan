<?php

namespace App\Enums;

enum TenantMembershipRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
