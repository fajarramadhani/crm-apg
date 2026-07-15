<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['final_priority_id' => ['required', 'integer', 'exists:ticket_priorities,id'], 'pic_user_id' => ['required', 'integer', 'exists:users,id'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
