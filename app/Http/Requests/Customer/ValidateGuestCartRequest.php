<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ValidateGuestCartRequest extends FormRequest
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
}
