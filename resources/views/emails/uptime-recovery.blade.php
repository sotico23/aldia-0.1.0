<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sitio Recuperado</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .success { background: #dcfce7; border: 1px solid #22c55e; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .success-title { color: #16a34a; font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .details { background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .footer { color: #6b7280; font-size: 12px; margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="success">
        <div class="success-title">✅ Sitio Recuperado</div>
        <p>El sitio <strong>{{ $site->name }}</strong> está nuevamente en línea y funcionando correctamente.</p>
        
        <div class="details">
            <p><strong>URL:</strong> {{ $site->url }}</p>
            <p><strong>Recuperado:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
            <p><strong>Tiempo fuera de servicio:</strong> {{ $downDuration }} minutos</p>
        </div>
    </div>
    
    <p>El incidente ha sido registrado en nuestro historial para su referencia.</p>
    
    <div class="footer">
        <p>Este es un mensaje automático de {{ config('app.name') }}</p>
    </div>
</body>
</html>
