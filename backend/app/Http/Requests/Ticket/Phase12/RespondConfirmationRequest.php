<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class RespondConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string',
            'notes' => 'nullable|string',
            'rejection_reason' => 'nullable|string|required_if:status,rejected',
        ];
    }
}
