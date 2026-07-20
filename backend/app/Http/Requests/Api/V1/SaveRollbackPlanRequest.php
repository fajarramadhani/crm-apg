<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveRollbackPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['rollback_trigger' => ['required', 'string', 'min:10'], 'rollback_steps' => ['required', 'array', 'min:1'], 'rollback_steps.*' => ['required', 'string'], 'data_recovery_steps' => ['nullable', 'array'], 'data_recovery_steps.*' => ['required', 'string'], 'estimated_rollback_minutes' => ['required', 'integer', 'min:1'], 'validation_after_rollback' => ['required', 'array', 'min:1'], 'validation_after_rollback.*' => ['required', 'string'], 'responsible_user_id' => ['required', 'integer', 'exists:users,id']];
    }
}
