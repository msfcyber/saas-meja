<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function store(
        SaveProductVariantRequest $request,
        Product $product,
        TenantContext $context,
    ): RedirectResponse {
        $this->authorize('update', $product);

        DB::transaction(function () use ($request, $product, $context): void {
            $attributes = $request->validated();

            if ($attributes['is_default']) {
                ProductVariant::query()->where('product_id', $product->id)->update(['is_default' => false]);
            }

            ProductVariant::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $context->outletOrFail()->id,
                'product_id' => $product->id,
                'position' => (ProductVariant::query()->where('product_id', $product->id)->max('position') ?? -1) + 1,
            ]);
        }, attempts: 3);

        return to_route('products')->with('success', 'Varian berhasil ditambahkan.');
    }

    public function update(SaveProductVariantRequest $request, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $variant);

        DB::transaction(function () use ($request, $variant): void {
            $attributes = $request->validated();

            if ($attributes['is_default']) {
                ProductVariant::query()
                    ->where('product_id', $variant->product_id)
                    ->whereKeyNot($variant)
                    ->update(['is_default' => false]);
            }

            $variant->update($attributes);
        }, attempts: 3);

        return to_route('products')->with('success', 'Varian berhasil diperbarui.');
    }

    public function destroy(ProductVariant $variant): RedirectResponse
    {
        $this->authorize('delete', $variant);
        $variant->delete();

        return to_route('products')->with('success', 'Varian berhasil dihapus.');
    }
}
