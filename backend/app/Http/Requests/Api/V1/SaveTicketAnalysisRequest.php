<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveTicketAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'problem_summary' => ['required', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:20000'],
            'technical_impact' => ['required', 'string', 'max:10000'],
            'business_impact' => ['nullable', 'string', 'max:5000'],
            'affected_components' => ['nullable', 'array', 'max:50'],
            'affected_components.*' => ['string', 'max:200', 'distinct'],
            'evidence' => ['nullable', 'string', 'max:10000'],
            'assumptions' => ['nullable', 'string', 'max:10000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'expected_lock_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
