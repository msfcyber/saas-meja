<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\SalesReportRequest;
use App\Models\Outlet;
use App\Services\SalesReportService;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function sales(
        SalesReportRequest $request,
        TenantContext $context,
        SalesReportService $reports,
    ): Response {
        $tenant = $context->tenantOrFail();

        return Inertia::render('reports/sales', [
            ...$reports->build($tenant, $request->validated()),
            'outlets' => Outlet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Outlet $outlet): array => [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                    'code' => $outlet->code,
                ])
                ->values(),
        ]);
    }
}
