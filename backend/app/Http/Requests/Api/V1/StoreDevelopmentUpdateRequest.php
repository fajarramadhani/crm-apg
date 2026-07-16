<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevelopmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['progress_percentage' => ['required', 'integer', 'between:0,100'], 'expected_progress' => ['required', 'integer', 'between:0,100'], 'summary' => ['required', 'string', 'max:10000'], 'completed_items' => ['nullable', 'array', 'max:100'], 'completed_items.*' => ['string', 'max:1000'], 'remaining_items' => ['nullable', 'array', 'max:100'], 'remaining_items.*' => ['string', 'max:1000'], 'blockers' => ['nullable', 'array', 'max:50'], 'blockers.*' => ['string', 'max:1000'], 'next_steps' => ['nullable', 'array', 'max:100'], 'next_steps.*' => ['string', 'max:1000']];
    }
}
