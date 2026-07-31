<?php

namespace Tests\Feature\Store;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StoreModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_logo_and_banner_url_resolve_from_the_s3_disk_when_set(): void
    {
        Storage::fake('s3');
        [$tenant] = $this->createTenantWithOwner();
        $store = $tenant->store;
        $store->update(['logo_path' => 'stores/logo.jpg', 'banner_path' => 'stores/banner.jpg']);

        $this->assertSame(Storage::disk('s3')->url('stores/logo.jpg'), $store->logo_url);
        $this->assertSame(Storage::disk('s3')->url('stores/banner.jpg'), $store->banner_url);
    }

    public function test_logo_and_banner_url_are_null_when_not_set(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        $this->assertNull($tenant->store->logo_url);
        $this->assertNull($tenant->store->banner_url);
    }

    public function test_to_storefront_array_shape(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $store = $tenant->store;
        $store->update([
            'name' => 'Dapur Ibu',
            'description' => 'Kue rumahan',
            'whatsapp_number' => '+6281234567890',
            'is_open' => true,
            'primary_color' => '#C2703D',
        ]);

        $this->assertSame([
            'name' => 'Dapur Ibu',
            'description' => 'Kue rumahan',
            'whatsappNumber' => '+6281234567890',
            'isOpen' => true,
            'primaryColor' => '#C2703D',
            'logoUrl' => null,
            'bannerUrl' => null,
        ], $store->toStorefrontArray());
    }
}
