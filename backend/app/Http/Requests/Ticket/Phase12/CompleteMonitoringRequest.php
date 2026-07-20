<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overall_result' => 'nullable|string',
            'summary' => 'nullable|string',
        ];
    }
}
