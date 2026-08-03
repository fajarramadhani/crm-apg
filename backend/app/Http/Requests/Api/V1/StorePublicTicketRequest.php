<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PublicIdentityNormalizer;
use App\Services\TicketDescriptionSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicTicketRequest extends FormRequest
{
    private const FORBIDDEN_FIELDS = [
        'requester_id', 'office_id', 'request_category', 'application_module_id',
        'requested_priority_id', 'final_priority_id', 'priority_id', 'status',
        'current_division_id', 'assigned_to', 'workflow_id', 'workflow_version',
        'workflow_mode', 'workflow_snapshot', 'current_workflow_stage', 'internal_note',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requester_name' => ['required', 'string', 'max:150'],
            'requester_email' => ['nullable', 'required_without:requester_phone', 'string', 'email:rfc', 'max:255'],
            'requester_phone' => ['nullable', 'required_without:requester_email', 'string', 'regex:/^628\d{8,11}$/'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'division_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')->where('is_active', true)],
            'ticket_category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'application_id' => ['required', 'integer', Rule::exists('applications', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'affected_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'reference' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'urgency' => ['required', Rule::in(['low', 'medium', 'high'])],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizer = app(PublicIdentityNormalizer::class);

        $this->merge([
            'requester_name' => trim((string) $this->input('requester_name')),
            'requester_email' => $normalizer->email($this->input('requester_email')),
            'requester_phone' => $normalizer->phone($this->input('requester_phone')),
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
            'affected_url' => $this->filled('affected_url') ? trim((string) $this->input('affected_url')) : null,
            'reference' => $this->filled('reference') ? trim((string) $this->input('reference')) : null,
            'urgency' => strtolower(trim((string) $this->input('urgency'))),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (self::FORBIDDEN_FIELDS as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, "The {$field} field cannot be specified.");
                }
            }

            foreach (['requester_name', 'requester_email', 'requester_phone', 'title', 'affected_url', 'reference'] as $field) {
                $value = (string) $this->input($field);
                if ($value !== strip_tags($value)) {
                    $validator->errors()->add($field, "The {$field} field cannot contain HTML.");
                }
            }

            if (! app(TicketDescriptionSanitizer::class)->hasMeaningfulText((string) $this->input('description'))) {
                $validator->errors()->add('description', 'Description must contain meaningful text.');
            }
        }];
    }
}
