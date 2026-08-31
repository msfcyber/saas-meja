<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? ($this->user()?->can('update', $product) ?? false)
            : ($this->user()?->can('create', Product::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', Rule::exists(Category::class, 'id')->where('tenant_id', $context->tenantId())->where('outlet_id', $context->outletId())],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', File::image()->max('5mb')->dimensions(Rule::dimensions()->maxWidth(2400)->maxHeight(2400))],
            'base_price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'is_active' => ['required', 'boolean'],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_available' => $this->boolean('is_available'),
            'is_featured' => $this->boolean('is_featured'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
