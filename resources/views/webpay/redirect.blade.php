<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectando con Transbank Webpay Plus...</title>
    <style nonce="{{ $cspNonce }}">
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background-color: #f8fafc; color: #334155; margin: 0; }
        .loader { border: 4px solid #e2e8f0; border-top-color: #4f46e5; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body onload="document.forms[0].submit()">
    <div class="loader"></div>
    <h3 style="margin: 0; font-weight: 600;">Redirigiendo a Webpay Plus...</h3>
    <p style="color: #64748b; font-size: 14px; margin-top: 8px;">Por favor, no cierres ni actualices esta ventana.</p>

    <!-- Transbank Autopost Form -->
    <form method="POST" action="{{ $url }}" style="display: none;">
        <input type="hidden" name="token_ws" value="{{ $token }}">
        <button type="submit">Enviar</button>
    </form>
</body>
</html>
