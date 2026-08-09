<?php

namespace App\Http\Requests\Api\Bot;

use App\Support\BotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['sometimes', 'nullable', 'date'],
            'metodo_pago' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tipo_documento' => ['sometimes', 'nullable', Rule::in(['boleta', 'factura', 'nota_credito', 'cotizacion'])],
            'estado' => ['sometimes', 'nullable', Rule::in(['pendiente', 'pagada', 'cancelada', 'completada'])],
            'notas' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function botContext(): BotContext
    {
        return $this->attributes->get('bot_context');
    }
}
