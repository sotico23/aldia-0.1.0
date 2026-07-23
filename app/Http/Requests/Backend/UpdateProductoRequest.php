<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productoId = $this->route('producto')?->id ?? $this->route('producto');

        return [
            'codigo' => ['sometimes', 'required', 'string', 'max:255', 'unique:productos,codigo,'.$productoId],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['sometimes', 'required', 'numeric', 'min:0'],
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
}
