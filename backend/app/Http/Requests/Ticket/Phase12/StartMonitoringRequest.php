<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class StartMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planned_end_at' => 'nullable|date',
        ];
    }
}
