<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('offices')
                    ->where(fn ($query) => $query->where('office_type', $this->input('office_type')))
                    ->ignore($this->route('office')),
            ],
            'office_type' => ['required', Rule::in(['pusat', 'cabang'])],
        ];
    }
}
