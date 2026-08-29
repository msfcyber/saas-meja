<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    OrderStatus::Accepted->value,
                    OrderStatus::Preparing->value,
                    OrderStatus::Ready->value,
                    OrderStatus::Served->value,
                    OrderStatus::Completed->value,
                    OrderStatus::Rejected->value,
                    OrderStatus::Cancelled->value,
                ]),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
