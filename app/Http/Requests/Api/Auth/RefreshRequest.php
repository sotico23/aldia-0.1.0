<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Token a rotar: llega en el body o en la cabecera Authorization.
     */
    public function plainToken(): string
    {
        $token = trim((string) $this->input('token', ''));

        if ($token === '') {
            $token = trim((string) ($this->bearerToken() ?? ''));
        }

        return $token;
    }
}
