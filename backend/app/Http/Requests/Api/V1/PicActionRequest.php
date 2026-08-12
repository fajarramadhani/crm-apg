<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class PicActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $path = $this->path();

        if (str_contains($path, '/work-notes')) {
            return [
                'content' => ['required', 'string', 'max:5000'],
                'visibility' => ['nullable', 'string', 'in:internal,requester_visible'],
            ];
        }

        if (str_contains($path, '/progress')) {
            return [
                'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ];
        }

        if (str_contains($path, '/request-info')) {
            return [
                'question' => ['required', 'string', 'max:2000'],
                'attachments' => ['nullable', 'array', 'max:5'],
                'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt,zip', 'max:10240'],
            ];
        }

        if (str_contains($path, '/waiting-external')) {
            return [
                'external_party_name' => ['required', 'string', 'max:255'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'follow_up_date' => ['nullable', 'date'],
                'notes' => ['required', 'string', 'max:2000'],
            ];
        }

        if (str_contains($path, '/internal-check')) {
            return [
                'result' => ['required', 'string', 'in:passed,needs_rework'],
                'notes' => ['required', 'string', 'max:2000'],
                'evidence_attachment_ids' => ['nullable', 'array'],
                'evidence_attachment_ids.*' => ['integer', 'exists:ticket_attachments,id'],
            ];
        }

        if (str_contains($path, '/submit-for-approval')) {
            return [
                'result_summary' => ['required', 'string', 'max:2000'],
                'internal_notes' => ['nullable', 'string', 'max:2000'],
                'requester_summary' => ['nullable', 'string', 'max:2000'],
                'attachment_ids' => ['nullable', 'array'],
                'attachment_ids.*' => ['integer', 'exists:ticket_attachments,id'],
            ];
        }

        if (str_contains($path, '/request-assistance')) {
            return [
                'reason' => ['required', 'string', 'max:2000'],
                'required_expertise' => ['nullable', 'string', 'max:255'],
                'suggested_pic_id' => ['nullable', 'integer', 'exists:users,id'],
            ];
        }

        if (str_contains($path, '/request-transfer')) {
            return [
                'reason' => ['required', 'string', 'max:2000'],
                'suggested_pic_id' => ['nullable', 'integer', 'exists:users,id'],
            ];
        }

        if (str_contains($path, '/attachments')) {
            return [
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt,zip', 'max:10240'],
                'visibility' => ['nullable', 'string', 'in:internal,requester_visible'],
                'category' => ['nullable', 'string', 'max:50'],
            ];
        }

        return [];
    }
}
