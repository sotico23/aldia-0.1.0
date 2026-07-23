<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Respuesta Lenta</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .warning { background: #fef9c3; border: 1px solid #eab308; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .warning-title { color: #ca8a04; font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .details { background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .footer { color: #6b7280; font-size: 12px; margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="warning">
        <div class="warning-title">⚠️ Respuesta Lenta</div>
        <p>El sitio <strong>{{ $site->name }}</strong> está respondiendo más lento de lo esperado.</p>
        
        <div class="details">
            <p><strong>URL:</strong> {{ $site->url }}</p>
            <p><strong>Tiempo de respuesta:</strong> {{ $responseTime }}ms</p>
            <p><strong>Umbral configurado:</strong> {{ $threshold }}ms</p>
        </div>
    </div>
    
    <p>El sitio sigue operativo, pero el rendimiento está por debajo del umbral esperado.</p>
    
    <div class="footer">
        <p>Este es un mensaje automático de {{ config('app.name') }}</p>
    </div>
</body>
</html>
