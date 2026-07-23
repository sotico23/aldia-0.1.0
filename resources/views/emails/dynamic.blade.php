<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Al Día') }}</h1>
    </div>
    <div class="content">
        {!! strip_tags($content, '<b><i><a><br><strong><ul><li><p><em><u><ol><span><div><table><tr><td><th><thead><tbody><tfoot><h1><h2><h3><h4><h5><h6><img><hr><blockquote><pre><code>') !!}
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} {{ config('app.name', 'Al Día') }}. Todos los derechos reservados.</p>
    </div>
</body>
</html>