<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletRefundDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUsePersonalWallet() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,reject,escalate,deny'],
            'response' => ['required_if:decision,reject,deny', 'nullable', 'string', 'max:1000'],
        ];
    }
}
