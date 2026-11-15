<?php

namespace App\Http\Requests\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterFreelancerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'telefono' => ['required', 'string', 'max:20'],
            'rut' => ['nullable', 'string', 'max:12'],
            'vehiculo_tipo' => ['required', 'string', 'in:bicicleta,moto,auto,camioneta,pie'],
            'radio_km' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'owner_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo ya está registrado. Inicia sesión para continuar tu solicitud.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'owner_id.exists' => 'El código de empresa no existe. Revisa el código o déjalo vacío para registrarte como independiente.',
            'owner_id.integer' => 'El código de empresa debe ser un número o quedar vacío.',
            'owner_id.min' => 'El código de empresa debe ser positivo o quedar vacío.',
            'vehiculo_tipo.in' => 'Tipo de vehículo no válido.',
        ];
    }
}
