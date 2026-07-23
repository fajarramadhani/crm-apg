<?php

namespace App\Http\Requests\Ticket\Phase12;

use App\Enums\MonitoringCheckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMonitoringCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'expected_condition' => ['nullable', 'string', 'max:5000'],
            'actual_result' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(MonitoringCheckStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_attachment_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_attachments', 'id')->where('ticket_id', $this->route('ticket')->id),
            ],
        ];
    }
}
