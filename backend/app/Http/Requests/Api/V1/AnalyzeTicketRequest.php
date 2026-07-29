<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AnalyzeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['supervisor_it', 'supervisor', 'it_lead']) ||
            $this->user()?->hasPermission('ticket.analysis.manage') ||
            $this->user()?->hasPermission('ticket.all.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'application_module_id' => ['nullable', 'integer', 'exists:application_modules,id'],
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'problem_source' => ['nullable', 'string', 'max:255'],
            'priority_id' => ['required', 'integer', 'exists:ticket_priorities,id'],
            'target_completion_date' => ['required', 'date', 'after_or_equal:today'],
            'analysis_notes' => ['nullable', 'string', 'max:5000'],
            'assign_primary_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'secondary_user_ids' => ['nullable', 'array'],
            'secondary_user_ids.*' => ['integer', 'exists:users,id'],
            'assignment_notes' => ['nullable', 'string', 'max:1000'],
            'assignment_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
