<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class RecordMonitoringCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => 'nullable|string',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'expected_condition' => 'nullable|string',
            'actual_result' => 'required|string',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'evidence_attachment_id' => 'nullable|integer',
        ];
    }
}
