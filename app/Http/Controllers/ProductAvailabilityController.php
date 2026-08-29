<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\UpdateProductAvailabilityRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductAvailabilityController extends Controller
{
    public function __invoke(UpdateProductAvailabilityRequest $request, Product $product): RedirectResponse
    {
        $product->update(['is_available' => $request->boolean('is_available')]);

        return back()->with('success', 'Ketersediaan produk diperbarui.');
    }
}
