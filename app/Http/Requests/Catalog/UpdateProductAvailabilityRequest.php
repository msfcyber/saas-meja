<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['is_available' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_available' => $this->boolean('is_available')]);
    }
}
