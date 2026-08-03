<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PublicIdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPublicHistoryChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_type' => ['required', Rule::in(['email'])],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identity_type' => strtolower(trim((string) $this->input('identity_type'))),
            'email' => app(PublicIdentityNormalizer::class)->email($this->input('email')),
        ]);
    }
}
