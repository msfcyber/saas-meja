<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $outlet = $context->outletOrFail();

        return Inertia::render('dashboard', [
            'outlet' => [
                'name' => $outlet->name,
                'timezone' => $outlet->timezone,
                'accepts_orders' => $outlet->accepts_orders,
            ],
            'today' => now($outlet->timezone)->translatedFormat('l, j F'),
            'catalogSummary' => [
                'products' => Product::query()->count(),
                'available_products' => Product::query()->where('is_active', true)->where('is_available', true)->count(),
                'active_tables' => DiningTable::query()->where('is_active', true)->count(),
                'total_tables' => DiningTable::query()->count(),
            ],
            'viewerName' => $request->user()->name,
        ]);
    }
}
