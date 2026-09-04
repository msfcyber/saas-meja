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
            'event' => ['required', 'string', Rule::in(AnalyticsEventService::PUBLIC_EVENTS)],
            'qr_token' => ['required', 'string', 'size:64', 'regex:/\A[a-fA-F0-9]+\z/'],
            'analytics_token' => ['required', 'string', 'max:512'],
            'product_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
