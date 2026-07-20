<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReleasePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'release_owner_id' => ['required', 'integer', 'exists:users,id'],
            'release_type' => ['required', Rule::in(['standard', 'normal', 'emergency'])],
            'target_environment' => ['required', Rule::in(['staging', 'production'])],
            'change_summary' => ['required', 'string', 'min:10'],
            'technical_summary' => ['required', 'string', 'min:10'],
            'affected_components' => ['required', 'array', 'min:1'],
            'affected_components.*' => ['required', 'string'],
            'dependencies' => ['nullable', 'array'],
            'database_changes' => ['nullable', 'string'],
            'data_migration_required' => ['required', 'boolean'],
            'downtime_required' => ['required', 'boolean'],
            'estimated_downtime_minutes' => ['nullable', 'integer', 'min:0'],
            'proposed_start_at' => ['nullable', 'date'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1'],
            'validation_steps' => ['required', 'array', 'min:1'],
            'validation_steps.*' => ['required', 'string'],
            'monitoring_plan' => ['required', 'array', 'min:1'],
            'monitoring_plan.*' => ['required', 'string'],
            'communication_notes' => ['nullable', 'string'],
            'expected_lock_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
