<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8px 10px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #444;
            margin: 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            border-bottom: 1px dashed #444;
            padding-bottom: 3px;
            font-size: 7.5px;
            text-align: left;
        }
        td {
            padding: 2.5px 0;
            vertical-align: top;
            font-size: 8px;
        }
        .totals td {
            padding: 1.5px 0;
        }
    </style>
</head>
<body>
    @php
        $docTitle = match($invoice->document_type) {
            '01' => 'FACTURA ELECTRÓNICA',
            '03' => 'BOLETA DE VENTA ELECTRÓNICA',
            default => 'NOTA DE VENTA',
        };
        $correlative = $invoice->series . '-' . str_pad((string) $invoice->number, 8, '0', STR_PAD_LEFT);
        $isElectronic = in_array($invoice->document_type, ['01', '03']);
    @endphp

    <!-- Cabecera Empresa -->
    <div class="center">
        <div class="bold" style="font-size: 12px;">{{ $company['trade_name'] ?? 'ELECTROREDES' }}</div>
        <div style="font-size: 7.5px;">{{ $company['name'] ?? '' }}</div>
        <div>RUC: {{ $company['ruc'] ?? '' }}</div>
        <div style="font-size: 7.5px;">{{ $company['address'] ?? '' }}</div>
        @if(!empty($company['phone']))
            <div>Tel: {{ $company['phone'] }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Datos del Comprobante -->
    <div class="center">
        <div class="bold" style="font-size: 9.5px;">{{ $docTitle }}</div>
        <div class="bold" style="font-size: 10px;">{{ $correlative }}</div>
    </div>

    <div class="divider"></div>

    <!-- Datos del Cliente -->
    <div>
        <div><span class="bold">Fecha:</span> {{ $invoice->created_at?->format('d/m/Y H:i') }}</div>
        <div><span class="bold">Cliente:</span> {{ $invoice->client_name }}</div>
        @if(!empty($invoice->client_doc_number) && $invoice->client_doc_number !== '00000000')
            <div>
                <span class="bold">{{ $invoice->client_doc_type === '6' ? 'RUC' : 'DNI/DOC' }}:</span>
                {{ $invoice->client_doc_number }}
            </div>
        @endif
        @if(!empty($invoice->client_address) && $invoice->client_address !== 'CIUDAD')
            <div><span class="bold">Dirección:</span> {{ $invoice->client_address }}</div>
        @endif
        <div><span class="bold">Pago:</span> {{ $invoice->payment_method }}</div>
        @if($invoice->user)
            <div><span class="bold">Cajero:</span> {{ $invoice->user->name }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Detalle de Productos -->
    <table>
        <thead>
            <tr>
                <th style="width: 14%;">CANT</th>
                <th style="width: 46%;">DESCRIPCIÓN</th>
                <th style="width: 18%;" class="right">P.U.</th>
                <th style="width: 22%;" class="right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $item)
                <tr>
                    <td>{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="right">{{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float)$item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Totales -->
    <table class="totals">
        @if($isElectronic)
            <tr>
                <td>OP. GRAVADA:</td>
                <td class="right">S/ {{ number_format((float)$invoice->op_taxed, 2) }}</td>
            </tr>
            <tr>
                <td>I.G.V. (18%):</td>
                <td class="right">S/ {{ number_format((float)$invoice->igv, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td class="bold" style="font-size: 9.5px;">TOTAL A PAGAR:</td>
            <td class="right bold" style="font-size: 9.5px;">S/ {{ number_format((float)$invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- QR SUNAT / Pie -->
    <div class="center">
        @if($isElectronic && !empty($qrBase64))
            <img src="{{ $qrBase64 }}" width="90" height="90" alt="QR SUNAT" style="margin: 4px 0;">
            <div style="font-size: 7px;">
                Representación impresa de la {{ ucfirst(strtolower($docTitle)) }}.
            </div>
            <div class="bold" style="font-size: 7px;">Estado SUNAT: {{ $invoice->sunat_status }}</div>
        @else
            <div class="bold" style="font-size: 7.5px;">DOCUMENTO INTERNO - SIN VALOR TRIBUTARIO</div>
        @endif

        <div class="bold" style="margin-top: 6px; font-size: 9px;">¡Gracias por su compra!</div>
        <div style="font-size: 7px;">Conserve este comprobante</div>
    </div>
</body>
</html>