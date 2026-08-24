<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication and active-account checks are enforced by the
        // auth:sanctum and active route middleware.
        return true;
    }

    /** @return array<string, array<mixed>|ValidationRule|string> */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var User|null $user */
                    $user = $this->user();

                    if (! $user instanceof User || ! Hash::check((string) $value, $user->password)) {
                        $fail('Password saat ini tidak sesuai.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:12', 'different:current_password', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru harus terdiri dari minimal 12 karakter.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ];
    }
}
