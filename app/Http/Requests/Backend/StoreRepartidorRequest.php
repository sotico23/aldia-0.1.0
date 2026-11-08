<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepartidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isNewUser = ! $this->filled('user_id');

        return [
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('repartidores', 'user_id'),
            ],
            'name' => [Rule::requiredIf($isNewUser), 'nullable', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($isNewUser),
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'rut' => ['nullable', 'string', 'max:20'],
            'estado' => ['required', Rule::in(['disponible', 'ocupado', 'offline'])],
            'radio_km' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'capacidad_max' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'Este usuario ya tiene un perfil de repartidor.',
            'name.required' => 'El nombre es obligatorio al crear un repartidor nuevo.',
            'email.required' => 'El correo es obligatorio al crear un repartidor nuevo.',
            'email.unique' => 'Este correo ya está registrado.',
            'estado.required' => 'El estado es obligatorio.',
            'radio_km.required' => 'El radio de cobertura es obligatorio.',
            'capacidad_max.required' => 'La capacidad máxima es obligatoria.',
        ];
    }
}
