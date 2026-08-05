<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPublicTicketActionChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['uat', 'confirmation'])],
            'challenge_token' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
