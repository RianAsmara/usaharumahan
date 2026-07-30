<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a name into a URL/SKU-safe identifier that is unique within a
 * tenant, appending "-2", "-3", ... on collision. Used for category slugs,
 * product slugs, and variant SKUs — anywhere the schema enforces
 * `unique(tenant_id, <column>)`.
 */
class UniqueSlug
{
    public static function generate(
        string $table,
        string $tenantId,
        string $source,
        string $column = 'slug',
        ?string $ignoreId = null,
    ): string {
        $base = Str::slug($source);
        $candidate = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where('tenant_id', $tenantId)
                ->whereRaw("LOWER($column) = LOWER(?)", [$candidate])
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
