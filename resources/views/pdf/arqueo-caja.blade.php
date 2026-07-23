<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arqueo de Caja</title>
    <style nonce="{{ $cspNonce }}">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #1a1a1a; padding: 30px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #111; padding-bottom: 15px; }
        .header h1 { font-size: 22px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 4px; }
        .header .subtitle { font-size: 11px; color: #666; }
        .meta-row { display: flex; margin-bottom: 20px; }
        .meta-table { width: 100%; margin-bottom: 20px; }
        .meta-table td { padding: 4px 8px; font-size: 10px; }
        .meta-table td.label { font-weight: 700; color: #555; text-transform: uppercase; width: 120px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .summary-table td { padding: 12px 16px; border: 1px solid #e0e0e0; }
        .summary-table .method-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; font-weight: 700; }
        .summary-table .method-value { font-size: 18px; font-weight: 900; }
        .summary-table .total-cell { background-color: #111; color: #fff; }
        .summary-table .total-cell .method-label { color: #aaa; }
        .summary-table .total-cell .method-value { color: #fff; }
        .efectivo .method-value { color: #16a34a; }
        .tarjeta .method-value { color: #2563eb; }
        .transferencia .method-value { color: #9333ea; }
        .detail-title { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th { background-color: #f5f5f5; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 700; color: #555; }
        .detail-table td { padding: 7px 10px; border-bottom: 1px solid #eee; font-size: 10px; }
        .detail-table tr:nth-child(even) { background-color: #fafafa; }
        .text-right { text-align: right; }
        .text-green { color: #16a34a; font-weight: 900; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #eee; text-align: center; font-size: 9px; color: #999; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Arqueo de Caja</h1>
        <div class="subtitle">Reporte generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Cajero:</td>
            <td>{{ $usuario }}</td>
            <td class="label">Período:</td>
            <td>{{ $fecha_desde }} — {{ $fecha_hasta }}</td>
        </tr>
        <tr>
            <td class="label">Total Transacciones:</td>
            <td>{{ $cantidad_ventas }}</td>
            <td class="label">Total Recaudado:</td>
            <td style="font-weight: 900; font-size: 13px;">${{ number_format($total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="efectivo" style="width:25%">
                <div class="method-label">Efectivo</div>
                <div class="method-value">${{ number_format($efectivo, 0, ',', '.') }}</div>
            </td>
            <td class="tarjeta" style="width:25%">
                <div class="method-label">Tarjetas</div>
                <div class="method-value">${{ number_format($tarjeta, 0, ',', '.') }}</div>
            </td>
            <td class="transferencia" style="width:25%">
                <div class="method-label">Transferencias</div>
                <div class="method-value">${{ number_format($transferencia, 0, ',', '.') }}</div>
            </td>
            <td class="total-cell" style="width:25%">
                <div class="method-label">Total Arqueo</div>
                <div class="method-value">${{ number_format($total, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <div class="detail-title">Detalle de Transacciones</div>
    <table class="detail-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Método</th>
                <th>Documento</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $item)
                <tr>
                    <td>{{ $item['fecha'] }}</td>
                    <td>{{ $item['hora'] }}</td>
                    <td>{{ $item['metodo'] }}</td>
                    <td>{{ $item['documento'] }}</td>
                    <td class="text-right text-green">${{ number_format($item['monto'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:20px; color:#999;">No hay transacciones en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Este documento es un comprobante interno de caja. Generado automáticamente por el sistema POS.
    </div>
</body>
</html>
