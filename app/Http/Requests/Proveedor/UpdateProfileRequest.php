<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:255',
            'direccion' => 'nullable|string|max:500',
        ];
    }
}
