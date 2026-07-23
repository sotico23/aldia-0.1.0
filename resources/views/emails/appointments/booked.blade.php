<x-mail::message>
# Hola {{ $appointment->client->name }},

Tu cita ha sido agendada correctamente.

**Detalles de la cita:**
- **Servicio:** {{ $appointment->producto->nombre }}
- **Fecha:** {{ $appointment->start_time->format('d/m/Y') }}
- **Hora:** {{ $appointment->start_time->format('H:i') }}
- **Lugar:** {{ $appointment->provider->publicProfile->title }}

<x-mail::button :url="config('app.url') . '/mis-pedidos'">
Ver mis reservas
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
