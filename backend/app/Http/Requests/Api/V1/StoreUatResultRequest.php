<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUatResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uat_scenario_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['accepted', 'rejected', 'blocked', 'not_run'])],
            'actual_result' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
