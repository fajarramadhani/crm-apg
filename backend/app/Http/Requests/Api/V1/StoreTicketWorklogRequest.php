<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['work_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subYear()->toDateString()], 'minutes_spent' => ['required', 'integer', 'min:1', 'max:1440'], 'activity_type' => ['required', Rule::in(['analysis_followup', 'development', 'configuration', 'data_fix', 'documentation', 'internal_testing', 'rework', 'other'])], 'description' => ['required', 'string', 'max:10000'], 'progress_after' => ['required', 'integer', 'between:0,100'], 'expected_progress' => ['required', 'integer', 'between:0,100']];
    }
}
