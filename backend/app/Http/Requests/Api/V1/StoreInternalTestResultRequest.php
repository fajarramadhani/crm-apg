<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternalTestResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['test_case_id' => ['required', 'integer', 'exists:ticket_internal_test_cases,id'], 'status' => ['required', Rule::in(['passed', 'failed', 'blocked', 'not_run'])], 'actual_result' => ['nullable', 'string', 'max:10000'], 'notes' => ['nullable', 'string', 'max:10000']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (in_array($this->input('status'), ['failed', 'blocked'], true) && blank($this->input('actual_result')) && blank($this->input('notes'))) {
                $validator->errors()->add('notes', 'Failed or blocked results require notes or an actual result.');
            }
        });
    }
}
