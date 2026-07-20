<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadReleaseEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimes:png,jpg,jpeg,pdf,txt,csv,doc,docx,xls,xlsx'], 'category' => ['required', Rule::in(['release_plan_evidence', 'rollback_plan_evidence', 'release_checklist_evidence', 'database_backup_plan', 'security_review_evidence'])]];
    }
}
