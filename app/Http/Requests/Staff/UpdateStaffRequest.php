<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof User
            && ($this->user()?->can('update', $staff) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['admin', 'cashier', 'kitchen'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
