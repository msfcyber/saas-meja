<?php

namespace App\Http\Requests\Tables;

use App\Models\DiningTable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $table = $this->route('table');

        return $table instanceof DiningTable
            ? ($this->user()?->can('update', $table) ?? false)
            : ($this->user()?->can('create', DiningTable::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique(DiningTable::class, 'code')
                    ->where('tenant_id', $context->tenantId())
                    ->where('outlet_id', $context->outletId())
                    ->ignore($this->route('table')),
            ],
            'zone' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
