<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variant = $this->route('variant');

        return $variant instanceof ProductVariant
            ? ($this->user()?->can('update', $variant) ?? false)
            : ($this->user()?->can('create', ProductVariant::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $variant = $this->route('variant');
        $product = $this->route('product');
        $productId = $variant instanceof ProductVariant
            ? $variant->product_id
            : ($product instanceof Product ? $product->getKey() : null);
        $nameRule = Rule::unique(ProductVariant::class, 'name')->where('product_id', $productId);

        if ($variant instanceof ProductVariant) {
            $nameRule->ignore($variant->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'price_delta' => ['required', 'integer', 'min:-999999999', 'max:999999999'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
