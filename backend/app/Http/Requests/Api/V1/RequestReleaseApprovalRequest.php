<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestReleaseApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['summary' => ['required', 'string', 'min:10'], 'business_impact' => ['nullable', 'string'], 'release_risk_level' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'proposed_release_at' => ['nullable', 'date']];
    }
}
