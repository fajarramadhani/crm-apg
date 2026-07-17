<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadUatEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:png,jpg,jpeg,pdf,txt,csv,doc,docx,xls,xlsx'],
            'category' => ['required', Rule::in(['uat_evidence', 'uat_finding_evidence', 'uat_retest_evidence', 'uat_signoff_document'])],
            'uat_finding_id' => ['nullable', 'integer'],
        ];
    }
}
