<?php

namespace App\Http\Requests\Catalog;

use App\Models\Modifier;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductModifiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'modifier_ids' => ['present', 'array'],
            'modifier_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(Modifier::class, 'id')
                    ->where('tenant_id', $context->tenantId())
                    ->where('outlet_id', $context->outletId()),
            ],
        ];
    }
}
