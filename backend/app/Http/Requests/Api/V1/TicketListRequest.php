<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(TicketStatus::class)], 'category' => ['nullable', 'integer', 'exists:ticket_categories,id'], 'application' => ['nullable', 'integer', 'exists:applications,id'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'requester' => ['nullable', 'integer', 'exists:users,id'], 'submitted_from' => ['nullable', 'date'], 'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
