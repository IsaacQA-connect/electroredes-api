<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $invoice->full_number }}</title>
    <style>
        @page {
            margin: 4px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
            padding: 4px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .border-bottom { border-bottom: 1px dashed #000; margin: 4px 0; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 2px 0;
            vertical-align: top;
        }
        .qr-container {
            margin-top: 8px;
            text-align: center;
        }
        .qr-container img {
            width: 90px;
            height: 90px;
        }
    </style>
</head>
<body>

    <!-- CABECERA DE EMPRESA -->
    <div class="text-center">
        <div class="font-bold" style="font-size: 11px;">{{ $company['name'] }}</div>
        <div>RUC: {{ $company['ruc'] }}</div>
        <div>{{ $company['address'] }}</div>
        <div>Telf: {{ $company['phone'] }}</div>
    </div>

    <div class="border-bottom"></div>

    <!-- DATOS DEL COMPROBANTE -->
    <div class="text-center font-bold" style="font-size: 10px;">
        {{ strtoupper($invoice->document_type_label) }}
    </div>
    <div class="text-center font-bold" style="font-size: 11px;">
        {{ $invoice->full_number }}
    </div>

    <div class="border-bottom"></div>

    <!-- DATOS DEL CLIENTE -->
    <div><span class="font-bold">Fecha:</span> {{ $invoice->created_at->format('d/m/Y H:i') }}</div>
    <div><span class="font-bold">Cliente:</span> {{ $invoice->client_name }}</div>
    <div>
        <span class="font-bold">
            {{ $invoice->client_doc_type === '6' ? 'RUC:' : ($invoice->client_doc_type === '1' ? 'DNI:' : 'Doc:') }}
        </span> 
        {{ $invoice->client_doc_number }}
    </div>
    @if($invoice->client_address)
        <div><span class="font-bold">Dir:</span> {{ $invoice->client_address }}</div>
    @endif
    <div><span class="font-bold">Pago:</span> {{ $invoice->payment_method }}</div>

    <div class="border-bottom"></div>

    <!-- TABLA DE DETALLES -->
    <table>
        <thead>
            <tr>
                <th style="width: 15%; text-align: left;">Cant</th>
                <th style="width: 55%; text-align: left;">Producto</th>
                <th style="width: 30%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $item)
                <tr>
                    <td>{{ (float)$item->quantity }}</td>
                    <td>
                        {{ $item->product_name }}<br>
                        <small style="color: #444;">@ S/ {{ number_format($item->unit_price, 2) }}</small>
                    </td>
                    <td class="text-right">S/ {{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-bottom"></div>

    <!-- TOTALES -->
    <table>
        <tr>
            <td class="text-right font-bold">Op. Gravada:</td>
            <td class="text-right" style="width: 35%;">S/ {{ number_format($invoice->op_taxed, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right font-bold">IGV (18%):</td>
            <td class="text-right">S/ {{ number_format($invoice->igv, 2) }}</td>
        </tr>
        <tr style="font-size: 11px;">
            <td class="text-right font-bold">TOTAL:</td>
            <td class="text-right font-bold">S/ {{ number_format($invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="border-bottom"></div>

    <!-- CÓDIGO QR Y LEYENDA SUNAT -->
    @if($invoice->document_type !== 'NV')
        <div class="qr-container">
            <img src="{{ $qrCode }}" alt="SUNAT QR">
        </div>
        <div class="text-center" style="font-size: 7px; margin-top: 4px;">
            Representación impresa del Comprobante de Pago Electrónico.<br>
            Consulte su documento en nuestra plataforma.
        </div>
    @else
        <div class="text-center font-bold" style="font-size: 8px; margin-top: 6px;">
            DOCUMENTO DE CONTROL INTERNO (NO VALIDO COMO COMPROBANTE DE PAGO)
        </div>
    @endif

    <div class="text-center" style="margin-top: 8px;">
        ¡Gracias por su compra!
    </div>

</body>
</html>