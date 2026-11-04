<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $documento }}</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        @page {
            margin: 14px 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.35;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .business-name {
            font-size: 17px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }

        .business-subtitle {
            color: #6b7280;
            font-size: 10px;
            margin-top: 3px;
        }

        .document-box {
            border: 2px solid #111827;
            padding: 7px 10px;
            text-align: center;
            min-width: 180px;
        }

        .document-box .label {
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #6b7280;
        }

        .document-box .number {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            margin-top: 2px;
        }

        .document-box .meta {
            font-size: 9px;
            color: #4b5563;
            margin-top: 3px;
        }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #374151;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px;
            margin: 0 0 6px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 6px 0 0;
        }

        .info-card {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 7px 9px;
            min-height: 58px;
        }

        .info-row {
            margin-bottom: 3px;
        }

        .info-row .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .info-row .value {
            font-weight: bold;
            color: #111827;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .items thead th {
            background: #f3f4f6;
            border-top: 1px solid #d1d5db;
            border-bottom: 1px solid #d1d5db;
            color: #374151;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            text-align: left;
        }

        .items tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 5px;
            vertical-align: top;
        }

        .items .right {
            text-align: right;
        }

        .items .center {
            text-align: center;
        }

        .totals {
            width: 46%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals td {
            padding: 3px 5px;
        }

        .totals .label {
            color: #4b5563;
        }

        .totals .value {
            text-align: right;
            font-weight: bold;
        }

        .totals .grand td {
            border-top: 2px solid #111827;
            padding-top: 6px;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        .dispatch-box {
            border: 1px solid #111827;
            border-radius: 4px;
            padding: 8px 10px;
            margin: 10px 0;
            background: #f9fafb;
        }

        .dispatch-box .dispatch-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 5px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }

        .signatures td {
            width: 50%;
            padding: 0 14px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #6b7280;
            padding-top: 4px;
            font-size: 9px;
            color: #4b5563;
        }

        .footer {
            margin-top: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            text-align: center;
            color: #9ca3af;
            font-size: 8px;
        }
    </style>
</head>
<body>
    @php
        $formatear = fn ($valor) => '$'.number_format((float) $valor, 0, ',', '.');

        $entrega = $venta->entrega;
        $esDespacho = (bool) $venta->has_delivery;

        $clienteNombre = $venta->delivery_recipient_name
            ?: $entrega?->cliente
            ?: $venta->cliente?->nombre
            ?: 'Cliente genérico';
        $clienteTelefono = $venta->delivery_phone
            ?: $entrega?->telefono
            ?: $venta->cliente?->telefono;
        $clienteDireccion = $venta->delivery_address
            ?: $entrega?->direccion
            ?: $venta->cliente?->direccion;
        $clienteComuna = $venta->delivery_commune
            ?: $entrega?->comuna
            ?: $venta->deliveryRate?->commune
            ?: $venta->cliente?->comuna;

        $conductorModel = $entrega?->getRelationValue('conductor');
        $conductor = $conductorModel?->nombre ?: $entrega?->getAttribute('conductor');
        $vendedor = $venta->empleado
            ? trim("{$venta->empleado->nombre} {$venta->empleado->apellido}")
            : ($venta->user?->name ?? 'No asignado');

        $empresa = $venta->owner?->business_name
            ?: ($venta->owner?->name ?? config('app.name'));

        $metodosPago = [
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Débito / Crédito',
            'visa_transbank' => 'Débito / Crédito',
            'transferencia' => 'Transferencia',
            'vale' => 'Vale',
            'binance' => 'Binance',
            'contactar' => 'Contactar',
        ];

        $metodoPago = $metodosPago[$venta->metodo_pago] ?? ucfirst((string) $venta->metodo_pago);

        $subtotalProductos = (float) $venta->subtotal - (float) $venta->delivery_cost;
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <p class="business-name">{{ $empresa }}</p>
                    <div class="business-subtitle">
                        Ticket de venta / Orden de despacho
                    </div>
                    <div class="business-subtitle">
                        Sucursal: {{ $venta->almacen?->nombre ?? 'Sin sucursal' }}
                    </div>
                </td>
                <td style="width: 190px;">
                    <div class="document-box">
                        <div class="label">{{ $esDespacho ? 'Orden de despacho' : 'Ticket de venta' }}</div>
                        <div class="number">{{ $documento }}</div>
                        <div class="meta">
                            {{ $venta->created_at?->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="info-grid">
        <tr>
            <td>
                <div class="info-card">
                    <p class="section-title">Cliente / Receptor</p>
                    <div class="info-row">
                        <span class="label">Nombre:</span>
                        <span class="value">{{ $clienteNombre }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Teléfono:</span>
                        <span class="value">{{ $clienteTelefono ?: 'No informado' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">RUT:</span>
                        <span class="value">{{ $venta->cliente?->rut ?: '—' }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="info-card">
                    <p class="section-title">Venta</p>
                    <div class="info-row">
                        <span class="label">Documento:</span>
                        <span class="value">{{ ucfirst((string) $venta->tipo_documento) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Pago:</span>
                        <span class="value">{{ $metodoPago }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Vendedor / Cajero:</span>
                        <span class="value">{{ $vendedor }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @if ($esDespacho)
        <div class="dispatch-box">
            <div class="dispatch-title">Datos de envío y asignación</div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                        <div class="info-row">
                            <span class="label">Comuna:</span>
                            <span class="value">{{ $clienteComuna ?: 'No informada' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Dirección de entrega:</span>
                            <span class="value">{{ $clienteDireccion ?: 'No informada' }}</span>
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <div class="info-row">
                            <span class="label">Repartidor asignado:</span>
                            <span class="value">{{ $conductor ?: 'Pendiente de asignación' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Costo de despacho:</span>
                            <span class="value">{{ $formatear($venta->delivery_cost) }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 48%;">Producto</th>
                <th class="center" style="width: 12%;">Cantidad</th>
                <th class="right" style="width: 20%;">Precio unit.</th>
                <th class="right" style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($venta->detalleVentas as $detalle)
                <tr>
                    <td>
                        {{ $detalle->producto?->nombre ?? 'Producto eliminado' }}
                        @if ($detalle->producto?->codigo)
                            <div style="color: #6b7280; font-size: 9px;">
                                Código: {{ $detalle->producto->codigo }}
                            </div>
                        @endif
                    </td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="right">{{ $formatear($detalle->precio_unitario) }}</td>
                    <td class="right">{{ $formatear($detalle->subtotal) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #6b7280; padding: 14px;">
                        Esta venta no tiene productos registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ $formatear($subtotalProductos) }}</td>
        </tr>
        @if ((float) $venta->descuento > 0)
            <tr>
                <td class="label">Descuento</td>
                <td class="value">-{{ $formatear($venta->descuento) }}</td>
            </tr>
        @endif
        @if ((float) $venta->monto_descuento_cupon > 0)
            <tr>
                <td class="label">Cupón</td>
                <td class="value">-{{ $formatear($venta->monto_descuento_cupon) }}</td>
            </tr>
        @endif
        @if ($esDespacho)
            <tr>
                <td class="label">Despacho</td>
                <td class="value">{{ $formatear($venta->delivery_cost) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">IVA incluido</td>
            <td class="value">{{ $formatear($venta->iva) }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="value">{{ $formatear($venta->total) }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">
                    Vendedor / Cajero: {{ $vendedor }}
                </div>
            </td>
            <td>
                <div class="signature-line">
                    Receptor: {{ $clienteNombre }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento generado automáticamente por {{ config('app.name') }}.
        @if ($venta->estado === 'anulada')
            Venta anulada.
        @endif
    </div>
</body>
</html>
