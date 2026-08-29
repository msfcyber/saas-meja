<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'size:64', 'regex:/\A[a-fA-F0-9]+\z/'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', 'string', Rule::in(['qris', 'ewallet', 'va'])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.modifier_option_ids' => ['sometimes', 'array', 'max:20'],
            'items.*.modifier_option_ids.*' => ['integer', 'distinct', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key'),
            'customer_name' => $this->filled('customer_name') ? trim((string) $this->input('customer_name')) : null,
        ]);
    }
}
