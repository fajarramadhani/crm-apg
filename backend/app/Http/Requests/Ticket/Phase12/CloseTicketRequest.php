<?php

namespace App\Http\Requests\Ticket\Phase12;

use Illuminate\Foundation\Http\FormRequest;

class CloseTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closure_summary' => 'required|string',
            'resolution_summary' => 'nullable|string',
            'business_outcome' => 'nullable|string',
            'final_sla_result' => 'nullable|string',
        ];
    }
}
