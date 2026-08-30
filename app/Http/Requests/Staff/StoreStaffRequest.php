<?php

namespace App\Http\Requests\Staff;

use App\Models\Outlet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'cashier', 'kitchen'])],
            'outlet_ids' => ['required', 'array', 'min:1'],
            'outlet_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Outlet::class, 'id')
                    ->where('tenant_id', $context->tenantId())
                    ->where('is_active', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
