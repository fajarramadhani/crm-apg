<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateActiveRelation($validator, 'role_id', Role::class);
            $this->validateActiveRelation($validator, 'division_id', Division::class);
            $this->validateActiveRelation($validator, 'branch_id', Branch::class);
        }];
    }

    protected function validateActiveRelation(Validator $validator, string $field, string $model): void
    {
        $id = $this->input($field);
        if ($id && ! $model::query()->whereKey($id)->where('is_active', true)->exists()) {
            $validator->errors()->add($field, 'Pilihan harus berstatus aktif.');
        }
    }
}
