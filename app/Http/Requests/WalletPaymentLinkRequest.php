<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class WalletPaymentLinkRequest extends WalletTransferRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return parent::authorize();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['account_number']);

        return $rules;
    }
}
