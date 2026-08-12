<?php

namespace App\Http\Requests\Api\V1;

use App\Services\TicketDescriptionSanitizer;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends StoreTicketRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('ticket')) === true;
    }

    public function rules(): array
    {
        if (! $this->user()?->hasRole('requester')) {
            return parent::rules();
        }

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'business_impact' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        if (! $this->user()?->hasRole('requester')) {
            return parent::after();
        }

        return [function (Validator $validator): void {
            if ($this->filled('description') && ! app(TicketDescriptionSanitizer::class)->hasMeaningfulText((string) $this->input('description'))) {
                $validator->errors()->add('description', 'Description cannot be empty or contain only whitespace.');
            }

            foreach (['request_category', 'application_id', 'urgency', 'status', 'workflow_id', 'primary_pic_id', 'secondary_pic_ids'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, "The {$field} field cannot be changed by Requester.");
                }
            }
        }];
    }
}
