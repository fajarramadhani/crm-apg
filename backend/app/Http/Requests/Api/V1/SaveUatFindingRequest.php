<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUatFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uat_run_id' => [Rule::requiredIf($this->isMethod('post')), 'integer'],
            'uat_scenario_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'business_impact' => ['nullable', 'string'],
            'severity' => ['required', Rule::in(['critical', 'major', 'minor', 'cosmetic'])],
        ];
    }
}
