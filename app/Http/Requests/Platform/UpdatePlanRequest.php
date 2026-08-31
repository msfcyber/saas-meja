<?php

namespace App\Http\Requests\Platform;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('plan');
        $planId = $plan instanceof Plan ? $plan->getKey() : null;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/\A[a-z0-9][a-z0-9_-]{1,48}\z/',
                Rule::unique('plans', 'code')->ignore($planId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'limits' => ['required', 'array'],
            'limits.outlets' => ['required', 'integer', 'min:-1', 'max:1000000'],
            'limits.active_tables' => ['required', 'integer', 'min:-1', 'max:1000000'],
            'limits.staff' => ['required', 'integer', 'min:-1', 'max:1000000'],
            'features' => ['nullable', 'array', 'max:50'],
            'features.*' => ['required', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower(trim((string) $this->input('code'))),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
        ]);
    }
}
