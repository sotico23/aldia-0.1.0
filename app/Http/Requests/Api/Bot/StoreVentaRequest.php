<?php

namespace App\Http\Requests\Api\Bot;

use App\Support\BotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownerId = $this->botContext()->ownerId;

        return [
            'cliente_id' => ['nullable', 'integer', $this->existsInTenant('clientes', 'cliente_id', $ownerId)],
            'fecha' => ['nullable', 'date'],
            'metodo_pago' => ['nullable', 'string', 'max:50'],
            'tipo_documento' => ['nullable', Rule::in(['boleta', 'factura', 'nota_credito', 'cotizacion'])],
            'es_pos' => ['nullable', 'boolean'],
            'estado' => ['nullable', Rule::in(['pendiente', 'pagada', 'cancelada', 'completada'])],
            'notas' => ['nullable', 'string', 'max:2000'],
            'numero' => ['nullable', 'string', 'max:50', Rule::unique('ventas', 'numero')],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', $this->existsInTenant('productos', 'producto_id', $ownerId)],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function botContext(): BotContext
    {
        return $this->attributes->get('bot_context');
    }

    protected function existsInTenant(string $table, string $attribute, int $ownerId): Exists
    {
        return Rule::exists($table, 'id')->where(fn ($q) => $q->where('owner_id', $ownerId));
    }
}
