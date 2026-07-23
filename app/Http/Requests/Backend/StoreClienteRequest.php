<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->check();
    }

    protected function prepareForValidation(): void
    {
        foreach (['nit', 'rut'] as $field) {
            if ($this->has($field) && ! empty($this->input($field))) {
                $value = $this->input($field);
                $clean = preg_replace('/[^k0-9a-zA-Z]/i', '', $value);
                if (strlen($clean) >= 2) {
                    $formatted = $this->formatRut($clean);
                    $this->merge([$field => $formatted]);
                }
            }
        }
    }

    private function formatRut(string $cleanRut): string
    {
        $rut = strtoupper($cleanRut);
        $dv = substr($rut, -1);
        $numero = substr($rut, 0, strlen($rut) - 1);
        $numero = strrev($numero);
        $numero = wordwrap($numero, 3, '.', true);
        $numero = strrev($numero);

        return $numero.'-'.$dv;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50', 'unique:clientes,nit'],
            'rut' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'giro' => ['nullable', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'activo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,bmp,webp,tiff,svg,heic,heif', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'nit.unique' => 'Este RUT ya está registrado.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
        ];
    }
}
