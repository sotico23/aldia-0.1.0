<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class LookupProductByBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ean' => $this->route('ean')]);
    }

    public function rules(): array
    {
        return [
            'ean' => ['required', 'string', 'regex:/^(?:\d{8}|\d{12,14})$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'ean.regex' => 'El código debe ser un UPC o EAN válido.',
        ];
    }
}
