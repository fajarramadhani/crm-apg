<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class StartDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',
        ];
    }
}
