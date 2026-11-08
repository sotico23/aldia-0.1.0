<?php

namespace App\Http\Requests\Backend;

use App\Models\Almacen;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpleadoRequest extends FormRequest
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
        $ownerId = $this->user()?->getOwnerId();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:2048'],
            'rut' => ['nullable', 'string', 'max:20', Rule::unique('empleados', 'rut')],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('empleados', 'email'),
                Rule::unique('users', 'email'),
            ],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'nacionalidad' => ['nullable', 'string', 'max:100'],
            'estado_civil' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'almacen_id' => [
                'nullable',
                'exists:almacenes,id',
                Rule::in(Almacen::where('owner_id', $ownerId)->pluck('id')),
            ],
            'fecha_contratacion' => ['nullable', 'date'],
            'tipo_contrato' => ['nullable', 'string', 'max:50'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'sueldo_liquido_pactado' => ['nullable', 'numeric', 'min:0'],
            'afp' => ['nullable', 'string', 'max:100'],
            'sistema_salud' => ['nullable', 'string', 'max:100'],
            'isapre_nombre' => ['nullable', 'string', 'max:100'],
            'banco_nombre' => ['nullable', 'string', 'max:100'],
            'banco_tipo_cuenta' => ['nullable', 'string', 'max:100'],
            'banco_numero_cuenta' => ['nullable', 'string', 'max:100'],
            'hora_entrada' => ['nullable', 'date_format:H:i'],
            'hora_salida' => ['nullable', 'date_format:H:i'],
            'estado' => ['required', 'string', 'max:50'],
            'notas' => ['nullable', 'string'],
            'crear_usuario' => ['nullable', 'boolean'],
            'es_repartidor' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
            'rol_id' => [
                Rule::excludeIf(fn () => $this->boolean('es_repartidor')),
                Rule::requiredIf(fn () => $this->boolean('crear_usuario') && ! $this->boolean('es_repartidor')),
                'nullable',
                'integer',
                'exists:roles,id',
                Rule::in(Role::where('owner_id', $ownerId)->pluck('id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'almacen_id.exists' => 'El almacén seleccionado no existe.',
            'rol_id.required' => 'El rol es obligatorio cuando se crea un usuario.',
            'rol_id.exists' => 'El rol seleccionado no existe.',
        ];
    }
}
