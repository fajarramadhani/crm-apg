<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends StoreAdminUserRequest
{
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
