<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_id.required' => 'Falta el identificador del desafio de verificacion.',
            'code.required' => 'Debes ingresar el codigo de verificacion.',
        ];
    }

    public function challengeId(): string
    {
        return trim((string) $this->input('challenge_id'));
    }

    public function code(): string
    {
        return trim((string) $this->input('code'));
    }
}
