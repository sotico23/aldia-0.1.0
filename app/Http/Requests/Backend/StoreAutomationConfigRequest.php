<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutomationConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->check();
    }

    public function rules(): array
    {
        return [
            'channel' => 'required|in:telegram,whatsapp,both',
            'frequency' => 'required|in:daily,weekly,monthly',
            'execution_time' => 'required|date_format:H:i',
            'enabled' => 'boolean',
            'selected_reports' => 'nullable|array',
            'selected_reports.*' => 'string|in:resumen_ejecutivo,ventas,inventario,stock_bajo,clientes_nuevos,clientes_inactivos,agenda_citas,gastos,flujo_caja,ctas_cobrar,ctas_pagar',
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Selecciona un canal de envío.',
            'channel.in' => 'El canal debe ser telegram, whatsapp o both.',
            'frequency.required' => 'Selecciona una frecuencia.',
            'frequency.in' => 'La frecuencia debe ser daily, weekly o monthly.',
            'execution_time.required' => 'Selecciona una hora de ejecución.',
            'execution_time.date_format' => 'La hora debe tener formato HH:MM.',
            'selected_reports.*.in' => 'Reporte no válido.',
        ];
    }
}
