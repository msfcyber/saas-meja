<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Enums\TenantStatus;
use App\Http\Requests\Onboarding\StoreOnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ((bool) $user->is_platform_admin) {
            return to_route('platform.dashboard');
        }

        $hasActiveWorkspace = $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', TenantStatus::Active)
            ->exists();

        if ($hasActiveWorkspace) {
            return to_route('dashboard');
        }

        if ($user->tenants()->exists()) {
            abort(403, 'Akun belum memiliki workspace aktif.');
        }

        return Inertia::render('onboarding/create', [
            'timezones' => [
                ['value' => 'Asia/Jakarta', 'label' => 'WIB - Jakarta'],
                ['value' => 'Asia/Makassar', 'label' => 'WITA - Makassar'],
                ['value' => 'Asia/Jayapura', 'label' => 'WIT - Jayapura'],
            ],
        ]);
    }

    public function store(StoreOnboardingRequest $request, CreateOwnerWorkspace $createWorkspace): RedirectResponse
    {
        if ((bool) $request->user()->is_platform_admin) {
            return to_route('platform.dashboard');
        }

        $workspace = $createWorkspace->handle($request->user(), $request->workspaceAttributes());

        $request->session()->put([
            'active_tenant_id' => $workspace['tenant']->getKey(),
            'active_outlet_id' => $workspace['outlet']->getKey(),
        ]);

        return to_route('dashboard')->with('success', 'Bisnis dan outlet pertama siap digunakan.');
    }
}
