<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class ReconcilePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
