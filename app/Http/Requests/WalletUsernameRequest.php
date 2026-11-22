<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUsePersonalWallet() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(preg_replace('/^@/', '', trim($this->input('username'))))]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^(?=.*[a-z])[a-z0-9_]+$/D'],
        ];
    }
}
