<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WalletRecipientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canUsePersonalWallet() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required_without:account_number', 'nullable', 'string', 'regex:/^@?(?=.*[a-z])[a-z0-9_]{3,30}$/iD'],
            'account_number' => ['required_without:username', 'prohibits:username', 'nullable', 'string', 'regex:/^[0-9]{16}$/D'],
        ];
    }
}
