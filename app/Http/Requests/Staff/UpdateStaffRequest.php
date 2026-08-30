<?php

namespace App\Http\Requests\Staff;

use App\Models\Outlet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof User
            && ($this->user()?->can('update', $staff) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'role' => ['required', Rule::in(['admin', 'cashier', 'kitchen'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
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
}
