<?php

namespace App\Http\Requests\Outlets;

use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletTaxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $outlet = $this->route('outlet');

        return $outlet instanceof Outlet
            && ($this->user()?->can('manageTax', $outlet) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $enabled = $this->boolean('tax_enabled');

        return [
            'tax_enabled' => ['required', 'boolean'],
            'tax_name' => [
                Rule::excludeIf(! $enabled),
                'required',
                'string',
                'max:50',
            ],
            'tax_rate' => [
                Rule::excludeIf(! $enabled),
                'required',
                'string',
                'regex:/\A\d+(?:\.\d{1,2})?\z/D',
                'gt:0',
                'lte:100',
            ],
            'tax_inclusive' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_name.required' => 'Nama pajak wajib diisi saat pajak diaktifkan.',
            'tax_rate.required' => 'Tarif pajak wajib diisi saat pajak diaktifkan.',
            'tax_rate.regex' => 'Tarif pajak harus berupa persen dengan maksimal dua angka desimal.',
            'tax_rate.gt' => 'Tarif pajak harus lebih besar dari 0%.',
            'tax_rate.lte' => 'Tarif pajak maksimal 100%.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if ($this->has('tax_enabled') && $this->input('tax_enabled') !== null) {
            $values['tax_enabled'] = $this->boolean('tax_enabled');
        }

        if ($this->has('tax_inclusive') && $this->input('tax_inclusive') !== null) {
            $values['tax_inclusive'] = $this->boolean('tax_inclusive');
        }

        if ($this->has('tax_name') && is_string($this->input('tax_name'))) {
            $values['tax_name'] = trim($this->input('tax_name'));
        }

        if ($this->has('tax_rate') && is_string($this->input('tax_rate'))) {
            $values['tax_rate'] = trim($this->input('tax_rate'));
        }

        $this->merge($values);
    }
}
