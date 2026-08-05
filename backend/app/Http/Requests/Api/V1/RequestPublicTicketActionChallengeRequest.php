<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PublicIdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPublicTicketActionChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['uat', 'confirmation'])], 'email' => ['required', 'string', 'email:rfc', 'max:255']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => strtolower(trim((string) $this->input('action'))),
            'email' => app(PublicIdentityNormalizer::class)->email($this->input('email')),
        ]);
    }
}
