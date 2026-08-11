<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Office;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreRequesterAccountRequest extends FormRequest
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
            'phone' => trim((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'office_mode' => ['required', Rule::in(['pusat', 'cabang'])],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[0-9+() .-]{7,30}$/'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['office_mode', 'office_id'])) {
                return;
            }

            $mode = $this->input('office_mode');
            $officeId = $this->input('office_id');

            if ($mode === 'cabang' && ! $officeId) {
                $validator->errors()->add('office_id', 'Pilih cabang terlebih dahulu.');

                return;
            }

            if ($officeId && ! Office::query()->whereKey($officeId)->where('office_type', $mode)->exists()) {
                $validator->errors()->add('office_id', 'Kantor tidak sesuai dengan tipe lokasi yang dipilih.');
            }
        }];
    }
}
