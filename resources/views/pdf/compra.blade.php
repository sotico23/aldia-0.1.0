<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra #{{ $compra->numero }}</title>
    <style nonce="{{ $cspNonce }}">
        @page { margin: 0cm 0cm; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header-bar {
            background: #e53935;
            color: white;
            padding: 12px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-bar .doc-type {
            font-size: 18px;
            font-weight: bold;
        }
        .header-bar .folio {
            font-size: 14px;
            text-align: right;
        }
        .content { padding: 20px 40px; }
        .section { margin-bottom: 16px; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #e53935;
            border-bottom: 2px solid #e53935;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        table { width: 100%; border-collapse: collapse; }
        table.items th {
            background: #e53935;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        table.items td {
            padding: 5px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }
        table.items tr:nth-child(even) td { background: #f9f9f9; }
        .totals { margin-top: 12px; text-align: right; }
        .totals table { width: auto; margin-left: auto; }
        .totals td { padding: 3px 12px; font-size: 10px; }
        .totals .total-row td { font-weight: bold; font-size: 12px; border-top: 2px solid #333; padding-top: 6px; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 8px 40px;
            border-top: 1px solid #ddd;
        }
        .info-grid { display: flex; gap: 30px; }
        .info-box { flex: 1; }
        .info-box p { margin: 2px 0; font-size: 9px; }
        .info-box .label { font-weight: bold; color: #666; }
        .logo { max-height: 50px; }
    </style>
</head>
<body>
    <div class="header-bar">
        <div>
            @if (file_exists($logo))
                <img src="{{ $logo }}" alt="Logo" class="logo" style="max-height:40px;">
            @endif
        </div>
        <div class="doc-type">ORDEN DE COMPRA</div>
        <div class="folio">
            <div>Folio: {{ $compra->numero }}</div>
            <div style="font-size:10px;">Fecha: {{ $compra->fecha?->format('d/m/Y') ?? $compra->created_at->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Información de la Empresa</div>
            <div class="info-grid">
                <div class="info-box">
                    <p><span class="label">{{ config('app.name') }}</span></p>
                    <p><span class="label">RUT:</span> {{ $compra->owner_id ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información del Proveedor</div>
            <div class="info-grid">
                <div class="info-box">
                    <p><span class="label">Nombre:</span> {{ $compra->proveedor->nombre }}</p>
                    <p><span class="label">NIT:</span> {{ $compra->proveedor->nit ?? '—' }}</p>
                    <p><span class="label">Teléfono:</span> {{ $compra->proveedor->telefono ?? '—' }}</p>
                    <p><span class="label">Email:</span> {{ $compra->proveedor->email ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Detalle de Productos</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:50px;">Código</th>
                        <th>Producto</th>
                        <th style="width:60px;text-align:center;">Cant.</th>
                        <th style="width:80px;text-align:right;">Precio Unit.</th>
                        <th style="width:80px;text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($compra->detalleCompras as $item)
                        <tr>
                            <td>{{ $item->producto?->codigo ?? '—' }}</td>
                            <td>{{ $item->producto?->nombre ?? 'Producto #'.$item->producto_id }}</td>
                            <td style="text-align:center;">{{ $item->cantidad }}</td>
                            <td style="text-align:right;">${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                            <td style="text-align:right;">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal:</td>
                        <td>${{ number_format($compra->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>IVA (19%):</td>
                        <td>${{ number_format($compra->iva, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total:</td>
                        <td>${{ number_format($compra->total, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if ($compra->notas)
            <div class="section">
                <div class="section-title">Notas</div>
                <p style="font-size:9px;color:#666;">{{ $compra->notas }}</p>
            </div>
        @endif

        <div class="section" style="margin-top:24px;">
            <div style="display:flex;gap:40px;margin-top:16px;">
                <div style="text-align:center;flex:1;">
                    <div style="border-top:1px solid #333;padding-top:4px;font-size:9px;">Firma Autorizada</div>
                </div>
                <div style="text-align:center;flex:1;">
                    <div style="border-top:1px solid #333;padding-top:4px;font-size:9px;">Recibí Conforme</div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} - {{ config('app.name') }}
    </div>
</body>
</html>
