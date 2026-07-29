<?php

namespace App\Rules;

use App\Models\StoreDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTenantSubdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Subdomain toko harus berupa teks.');

            return;
        }

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) || strlen($value) < 3 || strlen($value) > 63) {
            $fail('Subdomain toko hanya boleh berisi huruf kecil, angka, dan tanda hubung (3-63 karakter), tanpa hubung di awal/akhir.');

            return;
        }

        if (in_array($value, config('tenancy.reserved_subdomains', []), true)) {
            $fail('Subdomain ini sudah digunakan oleh platform dan tidak dapat dipakai.');

            return;
        }

        $hostname = $value.'.'.config('tenancy.root_domain');

        if (StoreDomain::query()->where('hostname', $hostname)->exists()) {
            $fail('Subdomain ini sudah dipakai toko lain.');
        }
    }
}
