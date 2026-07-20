<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => [$this->route('decision') === 'reject' ? 'required' : 'nullable', 'string'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
