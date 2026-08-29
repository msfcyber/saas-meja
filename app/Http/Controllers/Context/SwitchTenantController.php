<?php

namespace App\Http\Controllers\Context;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchTenantController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('switch', $tenant);

        $request->session()->put('active_tenant_id', $tenant->getKey());
        $request->session()->forget('active_outlet_id');

        return back();
    }
}
