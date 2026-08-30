<?php

namespace App\Http\Requests\Settings;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class RotateGatewayCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(TenantContext::class)->tenant();
        $user = $this->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && $user->can('gateway.manage')
            && $tenant->users()
                ->whereKey($user->getKey())
                ->wherePivot('status', 'active')
                ->wherePivot('is_owner', true)
                ->exists();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'server_key' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'server_key.required' => 'Midtrans Server Key wajib diisi.',
        ];
    }
}
