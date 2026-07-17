<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignQaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qa_user_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
