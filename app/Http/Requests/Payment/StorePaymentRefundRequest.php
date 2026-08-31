<?php

namespace App\Http\Requests\Payment;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('refund', $order) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => trim((string) $this->header('Idempotency-Key')),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }
}
