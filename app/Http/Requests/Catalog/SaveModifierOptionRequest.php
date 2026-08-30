<?php

namespace App\Http\Requests\Catalog;

use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveModifierOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $option = $this->route('option');

        return $option instanceof ModifierOption
            ? ($this->user()?->can('update', $option) ?? false)
            : ($this->user()?->can('create', ModifierOption::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $option = $this->route('option');
        $modifier = $this->route('modifier');
        $modifierId = $option instanceof ModifierOption
            ? $option->modifier_id
            : ($modifier instanceof Modifier ? $modifier->getKey() : null);
        $nameRule = Rule::unique(ModifierOption::class, 'name')->where('modifier_id', $modifierId);

        if ($option instanceof ModifierOption) {
            $nameRule->ignore($option->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'price_delta' => ['required', 'integer', 'min:-999999999', 'max:999999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
