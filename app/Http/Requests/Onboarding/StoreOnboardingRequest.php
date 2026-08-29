<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->tenants()->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:120'],
            'outlet_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-().\s]+$/'],
            'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'tax_enabled' => ['required', 'boolean'],
            'tax_name' => ['nullable', 'required_if:tax_enabled,true', 'string', 'max:50'],
            'tax_rate' => ['nullable', 'required_if:tax_enabled,true', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'tax_inclusive' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka dan simbol telepon umum.',
            'tax_name.required_if' => 'Nama pajak wajib diisi saat pajak diaktifkan.',
            'tax_rate.required_if' => 'Tarif pajak wajib diisi saat pajak diaktifkan.',
            'tax_rate.gt' => 'Tarif pajak harus lebih besar dari 0%.',
        ];
    }

    /**
     * @return array{business_name: string, outlet_name: string, address: string|null, phone: string|null, timezone: string, tax_enabled: bool, tax_name: string|null, tax_rate: float|null, tax_inclusive: bool}
     */
    public function workspaceAttributes(): array
    {
        return [
            'business_name' => $this->string('business_name')->toString(),
            'outlet_name' => $this->string('outlet_name')->toString(),
            'address' => $this->filled('address') ? $this->string('address')->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
            'timezone' => $this->string('timezone')->toString(),
            'tax_enabled' => $this->boolean('tax_enabled'),
            'tax_name' => $this->filled('tax_name') ? $this->string('tax_name')->toString() : null,
            'tax_rate' => $this->filled('tax_rate') ? (float) $this->input('tax_rate') : null,
            'tax_inclusive' => $this->boolean('tax_inclusive'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_enabled' => $this->boolean('tax_enabled'),
            'tax_inclusive' => $this->boolean('tax_inclusive'),
            'tax_name' => $this->input('tax_name'),
            'tax_rate' => $this->input('tax_rate'),
        ]);
    }
}
