<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandlePaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:150', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
            'event_type' => [
                'required',
                'string',
                Rule::in([
                    'payment.paid',
                    'payment.failed',
                    'payment.expired',
                    'payment.refunded',
                    'payment.partially_refunded',
                ]),
            ],
            'provider_reference' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/'],
            'occurred_at' => ['required', 'date'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
