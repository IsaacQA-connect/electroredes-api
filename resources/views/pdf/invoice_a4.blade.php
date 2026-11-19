<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
        }
        table { width: 100%; border-collapse: collapse; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .ruc-box {
            border: 1.5px solid #1e3a8a;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .ruc-box .doc-type {
            background: #1e3a8a;
            color: #fff;
            padding: 5px;
            margin: 6px 0;
            font-weight: bold;
            font-size: 11px;
        }
        .client-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 14px 0;
            background: #f9fafb;
        }
        .items th {
            background: #1e3a8a;
            color: #fff;
            padding: 7px 8px;
            font-size: 9.5px;
            text-align: left;
        }
        .items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 8px;
            font-size: 9.5px;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .total-final {
            background: #eff6ff;
            font-weight: bold;
            font-size: 11.5px;
            color: #1e3a8a;
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

    <!-- Cabecera -->
    <table>
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="bold" style="font-size: 17px; color: #1e3a8a;">{{ $company['trade_name'] ?? 'ELECTROREDES' }}</div>
                <div class="bold" style="font-size: 10.5px; margin-top: 2px;">{{ $company['name'] ?? '' }}</div>
                <div style="margin-top: 4px;">{{ $company['address'] ?? '' }}</div>
                @if(!empty($company['phone']))
                    <div>Teléfono: {{ $company['phone'] }}</div>
                @endif
                @if(!empty($company['email']))
                    <div>Correo: {{ $company['email'] }}</div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top;">
                <div class="ruc-box">
                    <div class="bold" style="font-size: 12px;">R.U.C. {{ $company['ruc'] ?? '' }}</div>
                    <div class="doc-type">{{ $docTitle }}</div>
                    <div class="bold" style="font-size: 13px;">{{ $correlative }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Datos del Cliente -->
    <div class="client-box">
        <table>
            <tr>
                <td style="width: 62%; padding: 2px 0;">
                    <span class="bold">Cliente:</span> {{ $invoice->client_name }}
                </td>
                <td style="width: 38%; padding: 2px 0;">
                    <span class="bold">Fecha de Emisión:</span> {{ $invoice->created_at?->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 2px 0;">
                    <span class="bold">{{ $invoice->client_doc_type === '6' ? 'RUC' : 'DNI / Doc.' }}:</span>
                    {{ ($invoice->client_doc_number && $invoice->client_doc_number !== '00000000') ? $invoice->client_doc_number : '-' }}
                </td>
                <td style="padding: 2px 0;">
                    <span class="bold">Método de Pago:</span> {{ $invoice->payment_method }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 2px 0;">
                    <span class="bold">Dirección:</span> {{ $invoice->client_address ?? 'CIUDAD' }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Detalle de Productos -->
    <table class="items">
        <thead>
            <tr>
                <th style="width: 10%;" class="center">CANT.</th>
                <th style="width: 54%;">DESCRIPCIÓN</th>
                <th style="width: 18%;" class="right">P. UNITARIO</th>
                <th style="width: 18%;" class="right">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $item)
                <tr>
                    <td class="center">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="right">S/ {{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="right">S/ {{ number_format((float)$item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pie: QR + Totales -->
    <table style="margin-top: 18px;">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                @if($isElectronic && !empty($qrBase64))
                    <table>
                        <tr>
                            <td style="width: 105px;">
                                <img src="{{ $qrBase64 }}" width="95" height="95" alt="QR SUNAT">
                            </td>
                            <td style="vertical-align: middle; font-size: 8.5px; color: #4b5563;">
                                <div class="bold">Representación impresa de la {{ ucfirst(strtolower($docTitle)) }}</div>
                                <div style="margin-top: 3px;">Estado SUNAT: <span class="bold">{{ $invoice->sunat_status }}</span></div>
                                @if(!empty($invoice->sunat_description))
                                    <div>{{ $invoice->sunat_description }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                @else
                    <div class="bold" style="font-size: 9px; color: #4b5563;">
                        DOCUMENTO INTERNO - SIN VALOR TRIBUTARIO
                    </div>
                @endif
            </td>
            <td style="width: 42%; vertical-align: top;">
                <table class="totals-table" style="border: 1px solid #d1d5db;">
                    @if($isElectronic)
                        <tr>
                            <td>Op. Gravada:</td>
                            <td class="right">S/ {{ number_format((float)$invoice->op_taxed, 2) }}</td>
                        </tr>
                        <tr>
                            <td>I.G.V. (18%):</td>
                            <td class="right">S/ {{ number_format((float)$invoice->igv, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-final">
                        <td>TOTAL A PAGAR:</td>
                        <td class="right">S/ {{ number_format((float)$invoice->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>