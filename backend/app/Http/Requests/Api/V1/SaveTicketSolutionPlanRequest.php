<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveTicketSolutionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solution_summary' => ['required', 'string', 'max:20000'],
            'implementation_steps' => ['required', 'array', 'min:1', 'max:100'],
            'implementation_steps.*.order' => ['required', 'integer', 'min:1', 'distinct'],
            'implementation_steps.*.description' => ['required', 'string', 'max:2000'],
            'affected_components' => ['nullable', 'array', 'max:50'],
            'affected_components.*' => ['string', 'max:200', 'distinct'],
            'dependencies' => ['nullable', 'array', 'max:50'],
            'dependencies.*' => ['string', 'max:500', 'distinct'],
            'estimated_effort_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'risk_level' => ['required', 'in:low,medium,high,critical'],
            'risk_description' => ['nullable', 'string', 'max:10000'],
            'rollback_plan' => ['nullable', 'required_if:risk_level,high,critical', 'string', 'max:20000'],
            'testing_plan' => ['required', 'string', 'max:20000'],
            'deployment_consideration' => ['nullable', 'string', 'max:10000'],
            'expected_lock_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
