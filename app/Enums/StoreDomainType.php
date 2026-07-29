<?php

namespace App\Enums;

enum StoreDomainType: string
{
    case Subdomain = 'subdomain';
    case Custom = 'custom';
}
