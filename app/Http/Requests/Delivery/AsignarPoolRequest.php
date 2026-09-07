<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class AsignarPoolRequest extends FormRequest
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
        return [
            'repartidor_id' => ['nullable', 'integer', 'exists:users,id'],
            'zona_id' => ['nullable', 'integer', 'exists:zones,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('repartidor_id') && ! $this->filled('zona_id')) {
                $validator->errors()->add('repartidor_id', 'Debes indicar un repartidor o una zona.');
            }
        });
    }
}
