<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin tenant management. Reachable only through routes/platform.php,
 * which gates the whole group behind the `platform.admin` middleware — see
 * docs/TENANCY.md §7. Mutating actions (suspend/reactivate) land in Phase 6;
 * this is the read-only list/detail view Phase 1 needs to prove the gate works.
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->with('owner:id,name,email')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('platform-admin/tenants/index', [
            'tenants' => $tenants,
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['owner:id,name,email', 'store', 'domains']);

        return Inertia::render('platform-admin/tenants/show', [
            'tenant' => $tenant,
        ]);
    }
}
