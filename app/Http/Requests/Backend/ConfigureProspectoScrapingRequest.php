<?php

namespace App\Http\Requests\Backend;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfigureProspectoScrapingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'node_binary' => ['nullable', 'string', 'max:500', 'not_regex:/[\r\n\x00]/'],
            'chrome_binary' => ['nullable', 'string', 'max:500', 'not_regex:/[\r\n\x00]/'],
            'queue_connection' => ['sometimes', 'required', 'in:database,redis,sync'],
            'queue_retry_after' => ['sometimes', 'required', 'integer', 'min:300', 'max:86400'],
        ];
    }
}
