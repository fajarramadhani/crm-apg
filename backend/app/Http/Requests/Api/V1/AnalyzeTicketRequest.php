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
            'analysis_summary' => ['required', 'string', 'max:5000'],
            'handling_note' => ['nullable', 'string', 'max:5000'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
            'application_id' => ['prohibited'],
            'application_module_id' => ['prohibited'],
            'ticket_category_id' => ['prohibited'],
            'request_category' => ['prohibited'],
            'urgency' => ['prohibited'],
            'priority_id' => ['prohibited'],
            'workflow_id' => ['prohibited'],
            'assign_primary_user_id' => ['prohibited'],
            'secondary_user_ids' => ['prohibited'],
            'primary_pic_id' => ['prohibited'],
            'secondary_pic_ids' => ['prohibited'],
        ];
    }
}
