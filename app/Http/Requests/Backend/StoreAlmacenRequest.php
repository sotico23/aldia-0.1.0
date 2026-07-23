<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlmacenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:almacenes,codigo'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'capacidad' => ['nullable', 'integer', 'min:0'],
            'tipo' => ['nullable', 'in:principal,secundario,distribucion,produccion'],
            'activo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del almacén es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
        ];
    }
}
