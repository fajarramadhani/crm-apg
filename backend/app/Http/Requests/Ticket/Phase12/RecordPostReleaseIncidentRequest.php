<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class RecordPostReleaseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => 'nullable|integer',
            'title' => 'required|string',
            'description' => 'required|string',
            'business_impact' => 'nullable|string',
            'severity' => 'nullable|string',
            'status' => 'nullable|string',
            'requires_rollback' => 'nullable|boolean',
        ];
    }
}
