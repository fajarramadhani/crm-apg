<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQaDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qa_test_run_id' => [Rule::requiredIf($this->isMethod('post')), 'integer'],
            'qa_test_case_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'severity' => ['required', Rule::in(['critical', 'major', 'minor', 'cosmetic'])],
            'priority' => ['required', Rule::in(['urgent', 'high', 'medium', 'low'])],
            'steps_to_reproduce' => ['required', 'string'],
            'expected_result' => ['required', 'string'],
            'actual_result' => ['required', 'string'],
            'environment' => ['nullable', 'string', 'max:120'],
        ];
    }
}
