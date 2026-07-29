<?php

namespace App\Services\Tenancy;

use App\Enums\DomainVerificationStatus;
use App\Models\StoreDomain;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Turns a raw Host header into a tenant, or null. See docs/TENANCY.md §2:
 * a hostname resolves to at most one tenant, and an unresolved hostname is
 * never allowed to fall back to some default tenant.
 */
class HostnameResolver
{
    public function resolve(string $rawHost): ?Tenant
    {
        $hostname = self::normalize($rawHost);

        // Only successful lookups are cached (forever, until explicitly
        // invalidated by a domain change) — an unresolved hostname is
        // retried on every request rather than being remembered as a
        // permanent miss, since it may simply not be verified yet.
        $tenantId = Cache::get(self::cacheKey($hostname));

        if ($tenantId === null) {
            $tenantId = StoreDomain::query()
                ->where('hostname', $hostname)
                ->where('verification_status', DomainVerificationStatus::Verified)
                ->value('tenant_id');

            if ($tenantId === null) {
                return null;
            }

            Cache::forever(self::cacheKey($hostname), $tenantId);
        }

        return Tenant::query()->whereKey($tenantId)->first();
    }

    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));

        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    public static function invalidate(string $hostname): void
    {
        Cache::forget(self::cacheKey(self::normalize($hostname)));
    }

    private static function cacheKey(string $hostname): string
    {
        return "tenancy:hostname:{$hostname}";
    }
}
