<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreTareaRequest extends FormRequest
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
            'empleado_id' => ['nullable', 'exists:users,id'],
            'estado' => ['nullable', 'in:pendiente,en_progreso,completada,cancelada'],
            'prioridad' => ['nullable', 'in:baja,media,alta,urgente'],
            'fecha_limite' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'fecha_limite.after_or_equal' => 'La fecha límite debe ser hoy o posterior.',
        ];
    }
}
