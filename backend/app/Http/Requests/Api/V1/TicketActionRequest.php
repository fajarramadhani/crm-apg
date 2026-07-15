<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TicketActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = in_array((string) $this->route()->getActionMethod(), ['requestRevision', 'reject', 'transfer'], true);

        return ['notes' => [$required ? 'required' : 'nullable', 'string', 'max:5000'], 'target_division_id' => [$this->route()->getActionMethod() === 'transfer' ? 'required' : 'nullable', 'integer', 'exists:divisions,id']];
    }
}
