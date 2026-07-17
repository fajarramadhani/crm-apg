<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQaTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_number' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'test_type' => ['required', Rule::in(['functional', 'regression', 'integration', 'usability', 'security', 'performance', 'compatibility', 'other'])],
            'preconditions' => ['nullable', 'string'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'string'],
            'expected_result' => ['required', 'string'],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'is_regression' => ['nullable', 'boolean'],
        ];
    }
}
