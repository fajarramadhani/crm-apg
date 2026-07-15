<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAttachment', $this->route('ticket')) === true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimetypes:image/png,image/jpeg,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'category' => ['nullable', Rule::in(['evidence', 'screenshot', 'document', 'log', 'other'])]];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->route('ticket')?->attachments()->count() >= 10) {
                $validator->errors()->add('file', 'A ticket may have at most 10 attachments.');
            }
        });
    }
}
