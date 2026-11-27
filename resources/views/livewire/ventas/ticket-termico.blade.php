<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $documento }}</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 4mm;
            width: 80mm;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .strong { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .small { font-size: 10px; }
        .muted { color: #444; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items th {
            border-bottom: 1px solid #000;
            padding: 3px 0;
            text-align: left;
            font-size: 10px;
        }

        .items td {
            padding: 3px 0;
            vertical-align: top;
        }

        .items .qty { width: 28px; }
        .items .desc { word-break: break-word; }

        .total { font-size: 13px; margin-top: 4px; }
        .footer { margin-top: 8px; }

        @media screen {
            body {
                background: #f1f5f9;
                padding: 16px;
                width: auto;
            }

            .ticket {
                background: #fff;
                max-width: 80mm;
                margin: 0 auto;
                padding: 4mm;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .18);
            }

            .controls {
                max-width: 80mm;
                margin: 0 auto 12px;
                font-family: Arial, sans-serif;
                font-size: 12px;
            }

            .controls button {
                font: inherit;
                padding: 8px 12px;
                margin-right: 6px;
                cursor: pointer;
            }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .ticket { padding: 0; box-shadow: none; }
            .controls { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="controls no-print">
        <button type="button" id="btn-imprimir">Imprimir ticket</button>
        <button type="button" id="btn-cerrar">Cerrar</button>
        <p class="small muted">Selecciona la impresora térmica (papel de 80 mm) en el diálogo de impresión.</p>
    </div>

    <div class="ticket">
        <p class="center strong upper">{{ $emisor?->business_name ?: $emisor?->name ?: 'Al Día' }}</p>
        @if($emisor?->rut)
            <p class="center small">RUT: {{ $emisor->rut }}</p>
        @endif
        @if($emisor?->direccion)
            <p class="center small">{{ $emisor->direccion }}</p>
        @endif
        @if($emisor?->telefono)
            <p class="center small">Tel: {{ $emisor->telefono }}</p>
        @endif

        <hr>

        <div class="row">
            <span class="strong">N° {{ $documento }}</span>
            <span>{{ $venta->fecha?->format('d-m-Y H:i') }}</span>
        </div>
        <div class="row">
            <span>Cliente:</span>
            <span>{{ $venta->cliente?->nombre ?: 'Cliente general' }}</span>
        </div>

        <hr>

        <table class="items">
            <thead>
                <tr>
                    <th class="qty">Cant</th>
                    <th>Producto</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->getItemsForTicket() as $group)
                    <tr>
                        <td class="qty">{{ number_format($group['item']->cantidad, 0, ',', '.') }}</td>
                        <td class="desc">
                            {{ $group['producto']->nombre }}
                            @if($group['tipo'] === 'envase_solo')
                                <span class="small">(envase)</span>
                            @endif
                        </td>
                        <td class="right">${{ number_format($group['item']->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($group['tipo'] === 'recarga' && $group['envase_item'])
                        <tr class="small">
                            <td class="qty">{{ number_format($group['envase_item']->cantidad, 0, ',', '.') }}</td>
                            <td class="desc">Envase: {{ $group['envase_item']->producto?->nombre ?: '—' }}</td>
                            <td class="right">${{ number_format($group['envase_item']->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <hr>

        <div class="row"><span>Subtotal</span><span>${{ number_format($venta->subtotal, 0, ',', '.') }}</span></div>
        @if($venta->iva > 0)
            <div class="row"><span>IVA</span><span>${{ number_format($venta->iva, 0, ',', '.') }}</span></div>
        @endif
        @if($venta->monto_descuento > 0)
            <div class="row"><span>Descuento</span><span>-${{ number_format($venta->monto_descuento, 0, ',', '.') }}</span></div>
        @endif
        <div class="row strong total"><span>TOTAL</span><span>${{ number_format($venta->total, 0, ',', '.') }}</span></div>
        <div class="row small"><span>Pago</span><span>{{ $venta->metodo_pago ?: '—' }}</span></div>

        <hr>

        <p class="center footer small">Gracias por su compra</p>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}">
        document.getElementById('btn-imprimir').addEventListener('click', function () {
            window.print();
        });
        document.getElementById('btn-cerrar').addEventListener('click', function () {
            window.close();
        });
        if (new URLSearchParams(window.location.search).has('autoprint')) {
            window.addEventListener('load', function () { window.print(); });
        }
    </script>
</body>
</html>
