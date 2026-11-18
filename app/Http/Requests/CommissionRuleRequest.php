<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommissionRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('finanzas.wallets.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'min_sale_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commission_type.required' => 'Debes indicar el tipo de comisión.',
            'commission_type.in' => 'El tipo de comisión debe ser porcentaje o monto fijo.',
            'commission_value.required' => 'Debes indicar el valor de la comisión.',
            'commission_value.numeric' => 'El valor de la comisión debe ser numérico.',
            'commission_value.min' => 'El valor de la comisión no puede ser negativo.',
            'min_sale_amount.numeric' => 'El monto mínimo de venta debe ser numérico.',
            'min_sale_amount.min' => 'El monto mínimo de venta no puede ser negativo.',
            'user_id.exists' => 'El cajero seleccionado no existe.',
        ];
    }
}
