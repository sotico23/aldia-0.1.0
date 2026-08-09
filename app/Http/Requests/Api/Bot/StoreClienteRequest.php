<?php

namespace App\Http\Requests\Api\Bot;

use App\Support\BotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownerId = $this->botContext()->ownerId;

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20', 'regex:/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/', $this->uniquePerTenant('clientes', 'rut', $ownerId)],
            'nit' => ['nullable', 'string', 'max:30', $this->uniquePerTenant('clientes', 'nit', $ownerId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'giro' => ['nullable', 'string', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono_contacto' => ['nullable', 'string', 'max:30'],
            'categoria_id' => ['nullable', 'integer',
                Rule::exists('categorias', 'id')->where(fn ($q) => $q->where('owner_id', $ownerId)),
            ],
            'activo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function botContext(): BotContext
    {
        return $this->attributes->get('bot_context');
    }

    protected function uniquePerTenant(string $table, string $column, int $ownerId): Unique
    {
        return Rule::unique($table, $column)->where(fn ($q) => $q->where('owner_id', $ownerId));
    }
}
