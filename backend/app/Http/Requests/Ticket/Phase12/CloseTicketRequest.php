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
            'resolution_summary' => 'required|string',
            'business_outcome' => 'required|string',
            'final_sla_result' => 'nullable|string',
        ];
    }
}
