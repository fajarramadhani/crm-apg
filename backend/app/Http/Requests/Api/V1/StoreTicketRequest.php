<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ApplicationModule;
use App\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('ticket.create') === true;
    }

    public function rules(): array
    {
        return [
            'ticket_category_id' => ['required', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'application_id' => ['nullable', Rule::exists('applications', 'id')->where('is_active', true)],
            'application_module_id' => ['nullable', Rule::exists('application_modules', 'id')->where('is_active', true)],
            'requested_priority_id' => ['nullable', Rule::exists('ticket_priorities', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:10000'],
            'business_impact' => ['nullable', 'string', 'max:5000'], 'urgency' => ['nullable', Rule::in(['low', 'normal', 'urgent', 'critical'])],
            'incident_occurred_at' => ['nullable', 'date', 'before_or_equal:now'], 'affected_url' => ['nullable', 'url:http,https', 'max:2048'],
            'expected_result' => ['nullable', 'string', 'max:5000'], 'actual_result' => ['nullable', 'string', 'max:5000'], 'reproduction_steps' => ['nullable', 'string', 'max:10000'],
            'request_purpose' => ['nullable', 'string', 'max:5000'], 'target_needed_at' => ['nullable', 'date'],
            'change_reason' => ['nullable', 'string', 'max:5000'], 'expected_impact' => ['nullable', 'string', 'max:5000'], 'recurring_indication' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = TicketCategory::query()->find($this->integer('ticket_category_id'));
            if ($category?->type === 'incident' && ! $this->filled('application_id')) {
                $validator->errors()->add('application_id', 'Application is required for incidents.');
            }
            if ($this->filled('application_module_id') && ! ApplicationModule::query()->whereKey($this->integer('application_module_id'))->where('application_id', $this->integer('application_id'))->exists()) {
                $validator->errors()->add('application_module_id', 'Module must belong to the selected application.');
            }
            foreach (['request' => 'request_purpose', 'change' => 'change_reason', 'problem' => 'recurring_indication'] as $type => $field) {
                if ($category?->type === $type && ! $this->filled($field)) {
                    $validator->errors()->add($field, "The {$field} field is required for {$type} tickets.");
                }
            }
        }];
    }
}
