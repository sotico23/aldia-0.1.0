<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreProyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'cliente' => ['nullable', 'string', 'max:255'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'gasto_real' => ['nullable', 'numeric', 'min:0'],
            'progreso' => ['nullable', 'integer', 'min:0', 'max:100'],
            'estado' => ['nullable', 'in:planificacion,en_progreso,pausado,completado,cancelado'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del proyecto es obligatorio.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'progreso.max' => 'El progreso no puede exceder el 100%.',
        ];
    }
}
