<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUsePersonalWallet() ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
