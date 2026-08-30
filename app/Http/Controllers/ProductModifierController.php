<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\UpdateProductModifiersRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductModifierController extends Controller
{
    public function update(UpdateProductModifiersRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $assignments = [];

        foreach ($request->array('modifier_ids') as $position => $modifierId) {
            if (! is_int($modifierId)) {
                continue;
            }

            $assignments[$modifierId] = [
                'tenant_id' => $product->tenant_id,
                'outlet_id' => $product->outlet_id,
                'position' => $position,
            ];
        }

        $product->modifiers()->sync($assignments);

        return to_route('products')->with('success', 'Modifier produk berhasil diperbarui.');
    }
}
