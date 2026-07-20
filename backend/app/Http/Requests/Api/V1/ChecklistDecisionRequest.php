<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChecklistDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['completed', 'blocked', 'not_applicable', 'pending'])], 'notes' => ['nullable', 'string'], 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
