<?php

namespace App\Http\Controllers\Context;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchOutletController extends Controller
{
    public function __invoke(Request $request, Outlet $outlet): RedirectResponse
    {
        Gate::authorize('view', $outlet);

        $request->session()->put('active_outlet_id', $outlet->getKey());

        return back();
    }
}
