<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Tenancy\CreateTenantWithStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\CreateTenantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->tenantMemberships()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/create', [
            'rootDomain' => config('tenancy.root_domain'),
        ]);
    }

    public function store(CreateTenantRequest $request, CreateTenantWithStore $createTenantWithStore): RedirectResponse
    {
        $tenant = $createTenantWithStore->handle(
            owner: $request->user(),
            name: $request->string('name')->toString(),
            subdomain: $request->string('subdomain')->toString(),
        );

        $request->session()->put('current_tenant_id', $tenant->id);

        return redirect()->route('dashboard');
    }
}
