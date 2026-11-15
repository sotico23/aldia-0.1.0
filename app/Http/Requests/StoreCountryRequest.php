<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'currency_code' => strtoupper(trim((string) $this->input('currency_code'))),
        ]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z]{2}$/', 'unique:countries,code'],
            'name' => ['required', 'string', 'max:100'],
            'currency_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_decimals' => ['required', 'integer', 'between:0,4'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone:all', 'max:50'],
            'phone_code' => ['required', 'string', 'regex:/^\\+[0-9]{1,4}$/'],
            'tax_name' => ['required', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'fiscal_id_label' => ['required', 'string', 'max:20'],
            'fiscal_id_pattern' => ['nullable', 'string', 'max:50'],
            'date_format' => ['required', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'código del país', 'name' => 'nombre',
            'currency_code' => 'código de moneda', 'currency_symbol' => 'símbolo',
            'currency_decimals' => 'decimales', 'locale' => 'locale',
            'timezone' => 'zona horaria', 'phone_code' => 'código de teléfono',
            'tax_name' => 'nombre del impuesto', 'tax_rate' => 'tasa del impuesto',
            'fiscal_id_label' => 'ID fiscal', 'date_format' => 'formato de fecha',
        ];
    }
}
