<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveModifierRequest;
use App\Models\Modifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class ModifierController extends Controller
{
    public function store(SaveModifierRequest $request, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Modifier::class);

        Modifier::query()->create([
            ...$request->validated(),
            'tenant_id' => $context->tenantId(),
            'outlet_id' => $context->outletOrFail()->id,
        ]);

        return to_route('products')->with('success', 'Modifier berhasil ditambahkan.');
    }

    public function update(SaveModifierRequest $request, Modifier $modifier): RedirectResponse
    {
        $this->authorize('update', $modifier);
        $modifier->update($request->validated());

        return to_route('products')->with('success', 'Modifier berhasil diperbarui.');
    }

    public function destroy(Modifier $modifier): RedirectResponse
    {
        $this->authorize('delete', $modifier);
        $modifier->delete();

        return to_route('products')->with('success', 'Modifier berhasil dihapus.');
    }
}
