<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRequesterTicketRequest extends FormRequest
{
    private const FORBIDDEN_FIELDS = [
        'requester_id',
        'division_id',
        'branch_id',
        'application_id',
        'application_module_id',
        'ticket_category_id',
        'requested_priority_id',
        'final_priority_id',
        'priority_id',
        'assigned_to',
        'pic_user_id',
        'workflow_id',
        'workflow_version',
        'workflow_mode',
        'workflow_snapshot',
        'current_workflow_stage',
        'status',
        'approver_id',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasPermission('ticket.create') || $user->hasRole('requester') || $user->role?->key === 'requester');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'affected_url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'reference' => ['nullable', 'string', 'max:255'],
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $payload = $this->all();

            foreach (self::FORBIDDEN_FIELDS as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                    $validator->errors()->add($field, "The {$field} field cannot be specified by Requester.");
                }
            }

            if ($this->filled('title') && trim($this->input('title')) === '') {
                $validator->errors()->add('title', 'Title cannot be empty or contain only whitespace.');
            }

            if ($this->filled('description') && trim($this->input('description')) === '') {
                $validator->errors()->add('description', 'Description cannot be empty or contain only whitespace.');
            }

            if ($this->filled('affected_url')) {
                $url = strtolower(trim($this->input('affected_url')));
                if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                    $validator->errors()->add('affected_url', 'The affected_url field must use http or https protocol.');
                }
            }
        }];
    }
}
