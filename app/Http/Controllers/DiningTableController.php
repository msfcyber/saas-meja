<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tables\StoreDiningTableRequest;
use App\Http\Requests\Tables\UpdateDiningTableRequest;
use App\Models\DiningTable;
use App\Services\SubscriptionEntitlementService;
use App\Services\TableQrCodeService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiningTableController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DiningTable::class);

        $zone = trim((string) $request->query('zone', ''));
        $tables = DiningTable::query()
            ->with('activeQrToken')
            ->when($zone !== '', fn ($query) => $query->where('zone', $zone))
            ->orderBy('zone')
            ->orderBy('code')
            ->get();

        return Inertia::render('tables', [
            'filters' => ['zone' => $zone ?: null],
            'summary' => [
                'tables' => DiningTable::query()->count(),
                'active_tables' => DiningTable::query()->where('is_active', true)->count(),
                'active_qr_tokens' => DiningTable::query()->has('activeQrToken')->count(),
                'zones' => DiningTable::query()->whereNotNull('zone')->distinct()->count('zone'),
            ],
            'zones' => DiningTable::query()->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone')->values(),
            'tables' => $tables->map(function (DiningTable $table): array {
                $qrToken = $table->activeQrToken;
                $hasArtifact = $qrToken?->qr_path !== null;

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'code' => $table->code,
                    'zone' => $table->zone,
                    'capacity' => $table->capacity,
                    'is_active' => $table->is_active,
                    'has_active_qr' => $qrToken !== null,
                    'qr_url' => $hasArtifact ? Storage::disk('public')->url($qrToken->qr_path) : null,
                    'qr_download_url' => $hasArtifact ? route('tables.qr.download', $table) : null,
                    'qr_print_url' => $hasArtifact ? route('tables.qr.print', $table) : null,
                ];
            })->values(),
        ]);
    }

    public function store(
        StoreDiningTableRequest $request,
        TenantContext $context,
        TableQrCodeService $qrCodes,
        SubscriptionEntitlementService $entitlements,
    ): RedirectResponse {
        $this->authorize('create', DiningTable::class);

        $tenant = $context->tenant();

        if ($tenant === null || ! $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_ACTIVE_TABLES)) {
            throw ValidationException::withMessages([
                'subscription' => $tenant === null
                    ? 'Workspace aktif tidak ditemukan.'
                    : $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_ACTIVE_TABLES),
            ]);
        }

        DB::transaction(function () use ($request, $context, $qrCodes): void {
            $table = DiningTable::query()->create([
                ...$request->validated(),
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $context->outletOrFail()->id,
            ]);

            $qrCodes->issue($table);
        }, attempts: 3);

        return to_route('tables')->with('success', 'Meja berhasil ditambahkan dengan QR aktif.');
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $this->authorize('update', $table);
        $table->update($request->validated());

        return to_route('tables')->with('success', 'Data meja berhasil diperbarui.');
    }
}
