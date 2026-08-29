<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Http\Requests\Onboarding\StoreOnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->tenants()->exists()) {
            return to_route('dashboard');
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
        $workspace = $createWorkspace->handle($request->user(), $request->workspaceAttributes());

        $request->session()->put([
            'active_tenant_id' => $workspace['tenant']->getKey(),
            'active_outlet_id' => $workspace['outlet']->getKey(),
        ]);

        return to_route('dashboard')->with('success', 'Bisnis dan outlet pertama siap digunakan.');
    }
}
