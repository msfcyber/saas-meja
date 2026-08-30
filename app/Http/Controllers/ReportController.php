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
        $isOwner = $tenant->membership?->is_owner === true;
        $filters = $request->validated();

        if (! $isOwner && empty($filters['outlet'])) {
            $filters['outlet'] = $context->outletId();
        }

        return Inertia::render('reports/sales', [
            ...$reports->build($tenant, $filters),
            'outlets' => ($isOwner
                ? Outlet::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())
                : $request->user()->assignedOutletsFor($tenant))
                ->where('outlets.is_active', true)
                ->orderBy('outlets.name')
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
