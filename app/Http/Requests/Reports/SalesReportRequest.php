<?php

namespace App\Http\Requests\Reports;

use App\Models\Outlet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('report.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        $tenant = $context->tenant();

        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'outlet' => [
                'nullable',
                'integer',
                Rule::exists(Outlet::class, 'id')
                    ->where('tenant_id', $context->tenantId())
                    ->where('is_active', true),
                Rule::when(
                    $tenant?->membership?->is_owner !== true,
                    Rule::exists('tenant_outlet_user', 'outlet_id')
                        ->where('tenant_id', $context->tenantId())
                        ->where('user_id', $this->user()?->getKey()),
                ),
            ],
        ];
    }
}
