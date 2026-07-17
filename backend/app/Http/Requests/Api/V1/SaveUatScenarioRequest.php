<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUatScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ticketId = $this->route('ticket')?->id;
        $scenarioId = $this->route('scenario')?->id;

        $unique = Rule::unique('ticket_uat_scenarios', 'scenario_number')
            ->where('ticket_id', $ticketId);

        if ($scenarioId) {
            $unique->ignore($scenarioId);
        }

        return [
            'scenario_number' => ['required', 'string', 'max:50', $unique],
            'title' => ['required', 'string', 'max:200'],
            'business_objective' => ['required', 'string'],
            'preconditions' => ['nullable', 'string'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'string'],
            'expected_result' => ['required', 'string'],
            'acceptance_criteria' => ['required', 'array', 'min:1'],
            'acceptance_criteria.*' => ['required', 'string'],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
        ];
    }
}
