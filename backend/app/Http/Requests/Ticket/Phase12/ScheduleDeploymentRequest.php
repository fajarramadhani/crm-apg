<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_start_at' => 'required|date',
            'scheduled_end_at' => 'nullable|date|after:scheduled_start_at',
            'environment' => 'nullable|string',
            'release_version' => 'nullable|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
