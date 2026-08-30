<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveModifierOptionRequest;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class ModifierOptionController extends Controller
{
    public function store(
        SaveModifierOptionRequest $request,
        Modifier $modifier,
        TenantContext $context,
    ): RedirectResponse {
        $this->authorize('update', $modifier);

        ModifierOption::query()->create([
            ...$request->validated(),
            'tenant_id' => $context->tenantId(),
            'outlet_id' => $context->outletOrFail()->id,
            'modifier_id' => $modifier->id,
            'position' => (ModifierOption::query()->where('modifier_id', $modifier->id)->max('position') ?? -1) + 1,
        ]);

        return to_route('products')->with('success', 'Opsi modifier berhasil ditambahkan.');
    }

    public function update(SaveModifierOptionRequest $request, ModifierOption $option): RedirectResponse
    {
        $this->authorize('update', $option);
        $option->update($request->validated());

        return to_route('products')->with('success', 'Opsi modifier berhasil diperbarui.');
    }

    public function destroy(ModifierOption $option): RedirectResponse
    {
        $this->authorize('delete', $option);
        $option->delete();

        return to_route('products')->with('success', 'Opsi modifier berhasil dihapus.');
    }
}
