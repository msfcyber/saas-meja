<?php

namespace App\Http\Requests\Outlets;

use App\Models\Outlet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Outlet::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/\A[A-Za-z0-9-]+\z/D',
                Rule::unique(Outlet::class, 'code')->where(
                    fn ($query) => $query->where('tenant_id', $context->tenantId()),
                ),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-().\s]+$/'],
            'timezone' => ['required', Rule::in($this->timezones())],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/D'],
            'is_active' => ['required', 'boolean'],
            'accepts_orders' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'currency' => strtoupper(trim((string) $this->input('currency', 'IDR'))),
            'is_active' => $this->boolean('is_active'),
            'accepts_orders' => $this->boolean('accepts_orders'),
        ]);
    }

    /** @return list<string> */
    private function timezones(): array
    {
        return ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
    }
}
