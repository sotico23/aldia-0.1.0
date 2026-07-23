<?php

namespace App\Traits;

use App\Models\Appointment;
use App\Models\Pedido;
use App\Scopes\OwnerScope;

trait ConfirmsAppointmentFromPedido
{
    private function confirmAppointmentFromPedido(Pedido $pedido): void
    {
        $appointmentId = $pedido->payment_data['appointment_id'] ?? null;
        if (! $appointmentId) {
            return;
        }

        $appointment = Appointment::withoutGlobalScope(OwnerScope::class)->find($appointmentId);
        if ($appointment && $appointment->payment_status !== 'pagado') {
            $appointment->update([
                'payment_status' => 'pagado',
                'status' => 'confirmada',
                'amount_paid' => $pedido->total,
            ]);
        }
    }
}
