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

        .header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 1.5cm;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        .header .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .header .info h1 {
            margin: 0;
            font-size: 20px;
            color: #111;
        }
        .header .info p {
            margin: 2px 0;
            color: #6b7280;
        }

        .title-section {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.5cm;
        }
        .title-section h2 {
            margin: 0;
            font-size: 16px;
            color: #374151;
            text-transform: uppercase;
        }
        .title-section .numero {
            font-size: 14px;
            color: #6b7280;
        }

        .client-section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 0.8cm;
            background-color: #f9fafb;
        }
        .client-section table {
            width: 100%;
        }
        .client-section td {
            vertical-align: top;
            padding: 3px 8px;
        }
        .client-section .label {
            font-weight: 600;
            color: #6b7280;
            width: 90px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background-color: #f3f4f6;
            color: #374151;
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .text-right {
            text-align: right;
        }

        .total-box {
            margin-top: 0.8cm;
            margin-left: auto;
            width: 250px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .total-box table {
            width: 100%;
        }
        .total-box td {
            padding: 4px 0;
        }
        .total-box .total-row {
            font-size: 16px;
            font-weight: bold;
            color: #111;
            border-top: 2px solid #d1d5db;
        }

        .footer {
            position: absolute;
            bottom: 2cm;
            left: 2cm;
            right: 2cm;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
            border-top: 1px solid #e5e7eb;
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
            opacity: 0.08;
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
        $settings = \App\Models\WebSetting::getSettings();
    @endphp

    <div class="header">
        @if(isset($logo) && file_exists($logo))
            <img src="{{ $logo }}" alt="Logo" class="logo">
        @endif
        <div class="info">
            <h1>{{ $settings->app_name ?? config('app.name') }}</h1>
            <p>{{ $settings->app_description ?? 'Documento de Venta' }}</p>
        </div>
    </div>

    <div class="title-section">
        <h2>Comprobante de Venta</h2>
        <span class="numero">N° {{ $venta->numero }}</span>
    </div>

    <div class="client-section">
        <table>
            <tr>
                <td class="label">Cliente:</td>
                <td>{{ $venta->cliente->nombre }}</td>
                <td class="label">Fecha:</td>
                <td class="text-right">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">RUT:</td>
                <td>{{ $venta->cliente->rut ?? 'N/A' }}</td>
                <td class="label">Estado:</td>
                <td class="text-right" style="text-transform: uppercase;">{{ $venta->estado }}</td>
            </tr>
            <tr>
                <td class="label">Dirección:</td>
                <td colspan="3">{{ $venta->cliente->direccion ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Email:</td>
                <td colspan="3">{{ $venta->cliente->email }}</td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-right" width="80">Cant.</th>
                <th class="text-right" width="100">P. Unit.</th>
                <th class="text-right" width="100">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->getItemsForTicket() as $group)
                @if($group['tipo'] === 'recarga')
                    <tr style="font-weight: bold;">
                        <td>{{ $group['producto']->nombre }}</td>
                        <td class="text-right">{{ number_format($group['item']->cantidad, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->precio_unitario, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($group['item']->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($group['envase_item'])
                        <tr style="font-size: 10px; color: #666;">
                            <td style="padding-left: 20px;">
                                <i class="fas fa-box"></i> Envase físico: {{ $group['envase_item']->producto->nombre ?? 'Cilindro' }}
                                <br>
                                <small style="color: #e65100;">
                                    Entregados: {{ number_format($group['envase_item']->cantidad, 0, ',', '.') }}
                                    @if($group['cantidad_retornada'] > 0)
                                        | Devueltos: {{ number_format($group['cantidad_retornada'], 0, ',', '.') }}
                                        | Faltantes: {{ number_format($group['envase_item']->cantidad - $group['cantidad_retornada'], 0, ',', '.') }}
                                    @endif
                                </small>
                            </td>
                            <td class="text-right">{{ number_format($group['envase_item']->cantidad, 0, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($group['envase_item']->precio_unitario, 0, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($group['envase_item']->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endif
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

    <div class="clear"></div>

    <div class="total-box">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">${{ number_format($venta->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if($venta->iva > 0)
            <tr>
                <td>IVA</td>
                <td class="text-right">${{ number_format($venta->iva, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="text-right">${{ number_format($venta->total, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if($venta->notas)
    <div style="margin-top: 0.5cm; padding: 10px 0; color: #6b7280; border-top: 1px solid #e5e7eb;">
        <strong>Notas:</strong><br>
        {{ $venta->notas }}
    </div>
    @endif

    <div class="footer">
        {{ $settings->app_name ?? config('app.name') }} — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
