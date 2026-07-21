<?php

namespace App\Http\Requests\Api\V1;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'status' => ['nullable', 'string'],
            'pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'environment' => ['nullable', 'string'],
            'release_risk_level' => ['nullable', 'string', 'in:low,medium,high,critical'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('date_from');
            $to = $this->input('date_to');

            if ($from && $to) {
                $fromDate = Carbon::parse($from);
                $toDate = Carbon::parse($to);

                if ($fromDate->diffInDays($toDate) > 366) {
                    $validator->errors()->add('date_to', 'The date range cannot exceed 366 days.');
                }
            }
        });
    }

    public function getValidDateFrom(): Carbon
    {
        return $this->filled('date_from')
            ? Carbon::parse($this->input('date_from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
    }

    public function getValidDateTo(): Carbon
    {
        return $this->filled('date_to')
            ? Carbon::parse($this->input('date_to'))->endOfDay()
            : now()->endOfDay();
    }
}
