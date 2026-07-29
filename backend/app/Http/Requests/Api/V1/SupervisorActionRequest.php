<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SupervisorActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['supervisor_it', 'supervisor', 'it_lead']) ||
            $this->user()?->hasPermission('ticket.all.view') ||
            $this->user()?->hasPermission('ticket.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();

        $rules = [
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'summary_for_requester' => ['nullable', 'string', 'max:5000'],
            'primary_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];

        if ($action === 'requestInfo') {
            $rules['notes'] = ['required', 'string', 'max:5000'];
        } elseif ($action === 'requestRevision') {
            $rules['notes'] = ['required', 'string', 'max:5000'];
        } elseif ($action === 'reject' || $action === 'cancel') {
            $rules['reason'] = ['required', 'string', 'max:5000'];
        } elseif ($action === 'reopen') {
            $rules['reason'] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }
}
