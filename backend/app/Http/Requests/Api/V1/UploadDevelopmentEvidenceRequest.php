<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDevelopmentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimetypes:image/png,image/jpeg,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'category' => ['required', Rule::in(['development_evidence', 'test_evidence', 'log', 'documentation'])], 'visibility' => ['nullable', Rule::in(['internal', 'requester'])]];
    }
}
