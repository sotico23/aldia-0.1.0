<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'fecha' => ['nullable', 'date'],
            'metodo_pago' => ['nullable', 'in:efectivo,tarjeta,transferencia,otro'],
            'tipo_documento' => ['nullable', 'in:boleta,factura,nota_credito,cotizacion'],
            'es_pos' => ['nullable', 'boolean'],
            'estado' => ['nullable', 'in:pendiente,pagada,cancelada'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:1'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'detalles.*.producto_id.required' => 'El producto es obligatorio.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }
}
