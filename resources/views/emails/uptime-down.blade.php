<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sitio Caído</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .alert { background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .alert-title { color: #dc2626; font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .details { background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .footer { color: #6b7280; font-size: 12px; margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="alert">
        <div class="alert-title">⛔ Sitio Caído</div>
        <p>El sitio <strong>{{ $site->name }}</strong> está experimentando problemas y no está respondiendo correctamente.</p>
        
        <div class="details">
            <p><strong>URL:</strong> {{ $site->url }}</p>
            <p><strong>Detectado:</strong> {{ $site->last_check_at?->format('Y-m-d H:i:s') ?? 'Ahora' }}</p>
            <p><strong>Última vez activo:</strong> {{ $downSince?->diffForHumans() ?? 'Desconocido' }}</p>
        </div>
    </div>
    
    <p>Se han registrado todos los detalles del incidente y nuestro equipo continuará monitoreando el sitio.</p>
    
    <div class="footer">
        <p>Este es un mensaje automático de {{ config('app.name') }}</p>
    </div>
</body>
</html>
