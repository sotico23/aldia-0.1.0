<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electronico es obligatorio.',
            'email.email' => 'El correo electronico no tiene un formato valido.',
            'password.required' => 'La contrasena es obligatoria.',
        ];
    }

    /**
     * Nombre del dispositivo al que se asociara el token.
     */
    public function deviceName(): string
    {
        $deviceName = trim((string) $this->input('device_name', ''));

        return $deviceName !== '' ? $deviceName : 'dispositivo-desconocido';
    }
}
