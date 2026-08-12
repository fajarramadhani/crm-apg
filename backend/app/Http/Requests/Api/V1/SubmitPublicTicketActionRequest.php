<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPublicTicketActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['accepted', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:5000', 'required_if:outcome,rejected'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'extensions:png,jpg,jpeg,pdf,txt,csv,doc,docx,xls,xlsx', 'mimetypes:image/png,image/jpeg,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'idempotency_key' => ['required', 'string', 'min:32', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => trim((string) $this->header('Idempotency-Key'))]);
    }
}
