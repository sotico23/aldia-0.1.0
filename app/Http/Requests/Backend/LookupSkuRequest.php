<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class LookupSkuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['codigo' => $this->route('codigo')]);
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El SKU es obligatorio.',
            'codigo.regex' => 'El SKU solo puede contener letras, números y guiones.',
        ];
    }
}
