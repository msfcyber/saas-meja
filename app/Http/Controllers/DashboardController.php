<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $outlet = $context->outletOrFail();
        $localNow = CarbonImmutable::now($outlet->timezone);
        $dayStart = $localNow->startOfDay()->setTimezone('UTC');
        $dayEnd = $dayStart->addDay();
        $salesStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Accepted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
            OrderStatus::Completed->value,
        ];
        $todaySales = Order::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $dayStart)
            ->where('paid_at', '<', $dayEnd)
            ->whereIn('status', $salesStatuses);

        return Inertia::render('dashboard', [
            'outlet' => [
                'name' => $outlet->name,
                'timezone' => $outlet->timezone,
                'currency' => $outlet->currency,
                'accepts_orders' => $outlet->accepts_orders,
            ],
            'today' => $localNow->translatedFormat('l, j F'),
            'catalogSummary' => [
                'products' => Product::query()->count(),
                'available_products' => Product::query()->where('is_active', true)->where('is_available', true)->count(),
                'active_tables' => DiningTable::query()->where('is_active', true)->count(),
                'total_tables' => DiningTable::query()->count(),
            ],
            'orderSummary' => [
                'orders_today' => (clone $todaySales)->count(),
                'gross_sales_today' => (clone $todaySales)->sum('grand_total'),
                'active_orders' => Order::query()->whereIn('status', [
                    OrderStatus::Paid->value,
                    OrderStatus::Accepted->value,
                    OrderStatus::Preparing->value,
                    OrderStatus::Ready->value,
                    OrderStatus::Served->value,
                ])->count(),
            ],
            'canViewReports' => $request->user()->can('report.view'),
            'viewerName' => $request->user()->name,
        ]);
    }
}
