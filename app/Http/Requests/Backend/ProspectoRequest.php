<?php

namespace App\Http\Requests\Backend;

use App\Rules\RutRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProspectoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'rut' => ['nullable', new RutRule],
            'email' => 'required|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'valor_estimado' => 'nullable|numeric|min:0',
            'fecha_seguimiento' => 'nullable|date',
            'prioridad' => 'required|string|in:Baja,Media,Alta',
            'estado' => 'required|string|max:50',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del prospecto es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto válida.',
            'email.required' => 'El correo electrónico es indispensable para el seguimiento.',
            'email.email' => 'Ingrese un formato de correo electrónico válido (ej: usuario@empresa.com).',
            'valor_estimado.numeric' => 'El valor estimado debe ser un número sin letras ni caracteres especiales.',
            'valor_estimado.min' => 'El valor estimado no puede ser negativo.',
            'fecha_seguimiento.date' => 'La fecha de seguimiento no tiene un formato de fecha reconocido.',
            'prioridad.in' => 'Seleccione una prioridad válida entre: Baja, Media o Alta.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rut' => 'RUT',
            'valor_estimado' => 'Valor Estimado',
        ];
    }
}
