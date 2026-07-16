<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInternalTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $case = $this->route('case');

        return ['case_number' => ['required', 'string', 'max:50', Rule::unique('ticket_internal_test_cases')->where('ticket_id', $ticket?->id)->ignore($case?->id)], 'title' => ['required', 'string', 'max:200'], 'preconditions' => ['nullable', 'string', 'max:10000'], 'steps' => ['required', 'array', 'min:1', 'max:100'], 'steps.*' => ['string', 'max:2000'], 'expected_result' => ['required', 'string', 'max:10000']];
    }
}
