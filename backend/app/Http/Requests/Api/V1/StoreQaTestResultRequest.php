<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQaTestResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qa_test_case_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['passed', 'failed', 'blocked', 'not_run'])],
            'actual_result' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
