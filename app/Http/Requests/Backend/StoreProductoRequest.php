<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255', 'unique:productos,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            'envase_retornable' => ['nullable', 'boolean'],
            'medida_pesable' => ['nullable', 'boolean'],
            'tipo_medida' => ['nullable', 'in:unidad,kilo,litro'],
            'cantidad_medida' => ['nullable', 'numeric', 'min:0'],
            'tipo_envase' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'mostrar_en_perfil' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del producto es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'precio_venta.numeric' => 'El precio debe ser un número.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
        ];
    }
}
