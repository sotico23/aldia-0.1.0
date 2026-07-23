<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CuponStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->check();
    }

    public function rules(): array
    {
        $ownerId = $this->user()->getOwnerId();

        return [
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('cupones', 'codigo')->where('owner_id', $ownerId),
            ],
            'tipo' => ['required', 'in:porcentaje,precio_fijo,envio_gratis,vale_producto'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'plantilla_html' => ['nullable', 'string'],
            'variables_ejemplo' => ['nullable', 'array'],
            'max_usos' => ['nullable', 'integer', 'min:0'],
            'usos_por_cliente' => ['nullable', 'integer', 'min:1'],
            'compra_minima' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activa' => ['boolean'],
            'productos' => ['required_if:tipo,vale_producto', 'array', 'min:1', 'max:20'],
            'productos.*.id' => ['required_with:productos', 'exists:productos,id'],
            'productos.*.descuento_tipo' => ['required_with:productos', 'in:porcentaje,precio_fijo'],
            'productos.*.descuento_valor' => ['required_with:productos', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del cupón es obligatorio.',
            'codigo.unique' => 'Ya existe un cupón con ese código.',
            'tipo.required' => 'Selecciona el tipo de descuento.',
            'tipo.in' => 'El tipo debe ser: porcentaje, precio fijo, envío gratis o vale por producto.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'productos.required_if' => 'Debes seleccionar al menos un producto para el tipo vale por producto.',
            'productos.min' => 'Debes seleccionar al menos un producto.',
            'productos.max' => 'Puedes seleccionar un máximo de 20 productos.',
            'productos.*.id.exists' => 'El producto seleccionado no existe.',
            'productos.*.descuento_tipo.required' => 'Selecciona el tipo de descuento para cada producto.',
            'productos.*.descuento_tipo.in' => 'El tipo de descuento debe ser porcentaje o precio fijo.',
            'productos.*.descuento_valor.required' => 'Ingresa el valor del descuento para cada producto.',
            'productos.*.descuento_valor.numeric' => 'El valor del descuento debe ser un número.',
            'productos.*.descuento_valor.min' => 'El valor del descuento no puede ser negativo.',
        ];
    }
}
