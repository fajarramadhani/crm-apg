<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class ManageDeploymentStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_number' => 'required|integer',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'step_type' => 'nullable|string',
            'is_required' => 'nullable|boolean',
            'status' => 'nullable|string',
            'started_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'evidence_attachment_id' => 'nullable|integer',
        ];
    }
}
