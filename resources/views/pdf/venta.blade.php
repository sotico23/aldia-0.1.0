<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Venta #{{ $venta->numero }}</title>
    <style nonce="{{ $cspNonce }}">
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 2cm;
            color: #333;
            font-size: 11px;
            line-height: 1.4;
        }
        /* SII Recuadro Rojo */
        .sii-header {
            position: absolute;
            top: 1.5cm;
            right: 1.5cm;
            border: 3px solid #FF0000;
            padding: 10px;
            text-align: center;
            width: 7cm;
            color: #FF0000;
            font-weight: bold;
        }
        .sii-header .rut {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .sii-header .tipo-doc {
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .sii-header .folio {
            font-size: 18px;
        }

        .header-info {
            margin-top: 0.5cm;
            width: 60%;
        }
        .header-info h1 {
            color: #000;
            font-size: 18px;
            margin: 0;
            text-transform: uppercase;
        }
        .header-info p {
            margin: 2px 0;
            color: #666;
        }

        .client-section {
            margin-top: 1.5cm;
            border: 1px solid #eee;
            padding: 15px;
            background-color: #fafafa;
        }
        .client-section table {
            width: 100%;
        }
        .client-section td {
            vertical-align: top;
            padding: 3px 0;
        }
        .label {
            font-weight: bold;
            color: #555;
            width: 100px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1cm;
        }
        .items-table th {
            background-color: #333;
            color: #fff;
            padding: 10px;
            text-align: left;
            text-transform: uppercase;
            font-size: 10px;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right;
        }

        .totals-section {
            margin-top: 1cm;
            width: 100%;
        }
        .totals-section table {
            width: 250px;
            float: right;
        }
        .totals-section td {
            padding: 5px 0;
        }
        .total-row {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            border-top: 2px solid #333;
        }

        .footer {
            position: absolute;
            bottom: 2cm;
            left: 2cm;
            right: 2cm;
            text-align: center;
            color: #999;
            font-size: 9px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .clear {
            clear: both;
        }
        #watermark {
            position: fixed;
            top: 25%;
            left: 5%;
            width: 90%;
            z-index: -1000;
            opacity: 0.1;
            text-align: center;
        }
        #watermark img {
            width: 12cm;
            height: auto;
        }
    </style>
</head>
<body>
    @if(isset($logo) && file_exists($logo))
    <div id="watermark">
        <img src="{{ $logo }}" alt="Watermark">
    </div>
    @endif
    @php
        $emisor = auth()->user();
        $tiposDoc = [
            'factura' => 'FACTURA ELECTRÓNICA',
            'boleta' => 'BOLETA ELECTRÓNICA',
            'nota_credito' => 'NOTA DE CRÉDITO',
            'cotizacion' => 'COTIZACIÓN',
        ];
        $tipoDocLabel = $tiposDoc[$venta->tipo_documento] ?? 'ORDEN DE VENTA';
    @endphp

    <div class="sii-header">
        <div class="rut">R.U.T.: {{ $emisor->rut ?? '76.000.000-0' }}</div>
        <div class="tipo-doc">{{ $tipoDocLabel }}</div>
        <div class="folio">
            @if($venta->folio)
                Folio: {{ $venta->folio }}
            @else
                N° {{ $venta->numero }}
            @endif
        </div>
    </div>

    <div class="header-info">
        <h1>{{ $emisor->razon_social ?? $emisor->name }}</h1>
        <p><strong>Giro:</strong> {{ $emisor->giro ?? 'Servicios Integrales' }}</p>
        <p>Casa Matriz: {{ $emisor->direccion ?? 'Santiago, Chile' }}</p>
        <p>Contacto: {{ $emisor->telefono ?? '' }} | {{ $emisor->email }}</p>
    </div>

    <div class="client-section">
        <table>
            <tr>
                <td class="label">Señor(es):</td>
                <td>{{ $venta->cliente->nombre }}</td>
                <td class="label">Fecha:</td>
                <td class="text-right">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">R.U.T.:</td>
                <td>{{ $venta->cliente->rut ?? 'N/A' }}</td>
                <td class="label">Estado:</td>
                <td class="text-right" style="text-transform: uppercase;">{{ $venta->estado }}</td>
            </tr>
            <tr>
                <td class="label">Dirección:</td>
                <td colspan="3">{{ $venta->cliente->direccion ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono:</td>
                <td>{{ $venta->cliente->telefono ?? 'N/A' }}</td>
                <td class="label">Email:</td>
                <td class="text-right">{{ $venta->cliente->email }}</td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-right" width="80">Cantidad</th>
                <th class="text-right" width="100">Precio Unit.</th>
                <th class="text-right" width="100">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->getItemsForTicket() as $group)
                @if($group['tipo'] === 'recarga')
                    {{-- Recarga con envase retornable --}}
                    <tr style="font-weight: bold;">
                        <td>{{ $group['producto']->nombre }}</td>
                        <td class="text-right">{{ number_format($group['item']->cantidad, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->precio_unitario, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($group['envase_item'])
                        <tr style="font-size: 10px; color: #666;">
                            <td style="padding-left: 20px;">
                                <span style="color: #e67e22;">●</span> Envase físico entregado: {{ $group['envase_item']->producto->nombre }}
                            </td>
                            <td class="text-right">{{ number_format($group['envase_item']->cantidad, 0, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($group['envase_item']->precio_unitario, 0, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($group['envase_item']->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    {{-- Envases devueltos / faltantes --}}
                    @if($group['cantidad_retornada'] > 0 || ($group['envase_item'] && $group['envase_item']->cantidad > ($group['cantidad_retornada'] ?? 0)))
                        <tr style="font-size: 9px; color: #888;">
                            <td style="padding-left: 20px;">
                                Envases devueltos: {{ $group['cantidad_retornada'] ?? 0 }}
                                @if($group['envase_item'] && $group['envase_item']->cantidad > ($group['cantidad_retornada'] ?? 0))
                                    | Faltantes: {{ $group['envase_item']->cantidad - ($group['cantidad_retornada'] ?? 0) }}
                                @endif
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    @endif
                @elseif($group['tipo'] === 'envase_solo')
                    <tr>
                        <td>{{ $group['producto']->nombre }} <span style="color: #e67e22;">(Envase suelto)</span></td>
                        <td class="text-right">{{ number_format($group['item']->cantidad, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->precio_unitario, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $group['producto']->nombre }}</td>
                        <td class="text-right">{{ number_format($group['item']->cantidad, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->precio_unitario, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <table>
            <tr>
                <td>Neto:</td>
                <td class="text-right">${{ number_format($venta->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>IVA (19%):</td>
                <td class="text-right">${{ number_format($venta->iva, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td class="text-right">${{ number_format($venta->total, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="clear"></div>

    @if($venta->notas)
    <div style="margin-top: 1cm; padding: 10px; border-left: 3px solid #eee; color: #666;">
        <strong>Notas:</strong><br>
        {{ $venta->notas }}
    </div>
    @endif

    <div class="footer">
        Documento generado por {{ config('app.name') }} - Fecha de Impresión: {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
