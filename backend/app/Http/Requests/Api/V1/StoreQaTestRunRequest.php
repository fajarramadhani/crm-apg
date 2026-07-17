<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreQaTestRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'environment' => ['required', 'string', 'max:120'],
            'build_reference' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
        ];
    }
}
