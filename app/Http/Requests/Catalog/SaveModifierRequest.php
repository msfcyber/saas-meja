<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ModifierSelectionType;
use App\Models\Modifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $modifier = $this->route('modifier');

        return $modifier instanceof Modifier
            ? ($this->user()?->can('update', $modifier) ?? false)
            : ($this->user()?->can('create', Modifier::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(TenantContext $context): array
    {
        $modifier = $this->route('modifier');
        $nameRule = Rule::unique(Modifier::class, 'name')
            ->where('tenant_id', $context->tenantId())
            ->where('outlet_id', $context->outletId());

        if ($modifier instanceof Modifier) {
            $nameRule->ignore($modifier->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'selection_type' => ['required', Rule::enum(ModifierSelectionType::class)],
            'minimum_selections' => [
                'required',
                'integer',
                'min:0',
                'max:50',
                'lte:maximum_selections',
                Rule::when($this->boolean('is_required'), ['min:1']),
            ],
            'maximum_selections' => [
                'required',
                'integer',
                'min:1',
                'max:50',
                'gte:minimum_selections',
                Rule::when($this->input('selection_type') === ModifierSelectionType::Single->value, ['max:1']),
            ],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
