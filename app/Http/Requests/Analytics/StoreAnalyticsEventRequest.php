<?php

namespace App\Http\Requests\Analytics;

use App\Services\AnalyticsEventService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticsEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(AnalyticsEventService::EVENTS)],
            'qr_token' => ['required', 'string', 'size:64', 'regex:/\A[a-fA-F0-9]+\z/'],
            'session_id' => ['required', 'string', 'max:120', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
            'product_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
