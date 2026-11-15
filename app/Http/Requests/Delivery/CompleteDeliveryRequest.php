<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteDeliveryRequest extends FormRequest
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
            'foto_entrega' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'cantidad_retornada' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'foto_entrega.required' => 'Debes tomar una foto para confirmar la entrega.',
            'foto_entrega.image' => 'La evidencia debe ser una imagen válida.',
            'foto_entrega.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'foto_entrega.max' => 'La foto no puede superar los 12 MB.',
        ];
    }
}
