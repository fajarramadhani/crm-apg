<?php

namespace App\Http\Requests\Ticket\Phase12;

use App\Enums\DeploymentStepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageDeploymentStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_number' => ['required', 'integer', 'min:1', 'max:1000'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'step_type' => ['nullable', 'string', 'max:100'],
            'is_required' => 'nullable|boolean',
            'status' => ['nullable', Rule::enum(DeploymentStepStatus::class)],
            'started_at' => 'nullable|date',
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_attachment_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_attachments', 'id')->where('ticket_id', $this->route('ticket')->id),
            ],
        ];
    }
}
