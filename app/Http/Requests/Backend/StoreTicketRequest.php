<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'producto_id' => ['nullable', 'exists:productos,id'],
            'prioridad' => ['nullable', 'in:baja,media,alta,urgente'],
            'estado' => ['nullable', 'in:abierto,en_progreso,resuelto,cerrado'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'asignado_a' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
        ];
    }
}
