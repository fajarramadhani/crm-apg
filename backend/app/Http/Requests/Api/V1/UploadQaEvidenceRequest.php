<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadQaEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:image/png,image/jpeg,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'category' => [
                'required',
                Rule::in(['qa_evidence', 'defect_evidence', 'retest_evidence']),
            ],
            'defect_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_qa_defects', 'id')->where(function ($query) {
                    $query->where('ticket_id', $this->route('ticket')?->id);
                }),
            ],
        ];
    }
}
