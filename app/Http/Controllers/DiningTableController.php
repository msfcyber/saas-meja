<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tables\StoreDiningTableRequest;
use App\Models\DiningTable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'tables' => $tables->map(fn (DiningTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'code' => $table->code,
                'zone' => $table->zone,
                'capacity' => $table->capacity,
                'is_active' => $table->is_active,
                'has_active_qr' => $table->activeQrToken !== null,
            ])->values(),
        ]);
    }

    public function store(StoreDiningTableRequest $request, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', DiningTable::class);

        DiningTable::query()->create([
            ...$request->validated(),
            'tenant_id' => $context->tenantId(),
            'outlet_id' => $context->outletOrFail()->id,
        ]);

        return to_route('tables')->with('success', 'Meja berhasil ditambahkan. Buat QR setelah public QR flow tersedia.');
    }
}
