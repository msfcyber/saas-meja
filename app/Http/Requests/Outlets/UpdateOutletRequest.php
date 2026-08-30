<?php

namespace App\Http\Requests\Outlets;

use App\Models\Outlet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        $outlet = $this->route('outlet');

        return $outlet instanceof Outlet
            && ($this->user()?->can('update', $outlet) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        $outlet = $this->route('outlet');
        $codeRule = Rule::unique(Outlet::class, 'code')->where(
            fn ($query) => $query->where('tenant_id', $context->tenantId()),
        );

        if ($outlet instanceof Outlet) {
            $codeRule->ignore($outlet->getKey());
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/\A[A-Za-z0-9-]+\z/D',
                $codeRule,
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\-().\s]+$/'],
            'timezone' => ['sometimes', 'required', Rule::in($this->timezones())],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/D'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'accepts_orders' => ['sometimes', 'required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if ($this->has('name')) {
            $values['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('code')) {
            $values['code'] = strtoupper(trim((string) $this->input('code')));
        }

        if ($this->has('currency')) {
            $values['currency'] = strtoupper(trim((string) $this->input('currency')));
        }

        if ($this->has('is_active')) {
            $values['is_active'] = $this->boolean('is_active');
        }

        if ($this->has('accepts_orders')) {
            $values['accepts_orders'] = $this->boolean('accepts_orders');
        }

        $this->merge($values);
    }

    /** @return list<string> */
    private function timezones(): array
    {
        return ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
    }
}
