<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:empleados,email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'fecha_contratacion' => ['nullable', 'date'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['nullable', 'in:activo,inactivo,vacaciones,licencia'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'almacen_id' => ['nullable', 'exists:almacenes,id'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'almacen_id.exists' => 'El almacén seleccionado no existe.',
        ];
    }
}
