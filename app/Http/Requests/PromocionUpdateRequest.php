<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromocionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->check();
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo' => ['sometimes', 'required', 'in:porcentaje,precio_fijo,combo_2x1'],
            'valor' => ['sometimes', 'required', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'skus' => ['nullable', 'array'],
            'skus.*' => ['string'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'compra_minima' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activa' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la promoción es obligatorio.',
            'tipo.required' => 'Selecciona el tipo de descuento.',
            'tipo.in' => 'El tipo de descuento debe ser: porcentaje, precio fijo o combo 2x1.',
            'valor.required' => 'El valor del descuento es obligatorio.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
