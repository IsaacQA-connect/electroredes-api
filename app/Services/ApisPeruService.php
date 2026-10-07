<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class ApisPeruService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.apisperu.token');
        $this->baseUrl = config('services.apisperu.url');
    }

    /**
     * Envía un comprobante (Factura/Boleta) a SUNAT mediante APIsPeru.
     */
    public function sendInvoice(Invoice $invoice): array
    {
        $payload = [
            'ublVersion'      => '2.1',
            'tipoDoc'          => $invoice->document_type, // '01' Factura, '03' Boleta
            'serie'            => $invoice->series,        // Ej: 'F001' o 'B001'
            'correlativo'      => (string) $invoice->number,
            'fechaEmision'     => $invoice->created_at->format('Y-m-d\TH:i:sP'),
            'formaPago'        => [
                'moneda' => $invoice->currency,
                'tipo'   => 'Contado',
            ],
            'tipoMoneda'       => $invoice->currency,
            'client'           => [
                'tipoDoc' => $invoice->client_doc_type, // '6' RUC, '1' DNI, '0' Sin Doc
                'numDoc'  => $invoice->client_doc_number,
                'rznSocial' => $invoice->client_name,
                'address' => [
                    'direccion' => $invoice->client_address ?? 'CIUDAD',
                ],
            ],
            'company'          => [
                'ruc'             => config('app.company_ruc', '20000000000'),
                'razonSocial'     => config('app.company_name', 'MI EMPRESA S.A.C.'),
                'nombreComercial' => config('app.company_commercial_name', 'MI EMPRESA'),
                'address'         => [
                    'direccion'    => 'AV. PRINCIPAL 123',
                    'departamento' => 'MOQUEGUA',
                    'provincia'    => 'MARISCAL NIETO',
                    'distrito'     => 'MOQUEGUA',
                    'ubigeo'       => '180101',
                ],
            ],
            'montoOperGravadas' => (float) $invoice->op_taxed,
            'montoIGV'          => (float) $invoice->igv,
            'totalImpuestos'    => (float) $invoice->igv,
            'valorVenta'        => (float) $invoice->op_taxed,
            'subTotal'          => (float) $invoice->total,
            'montoImpVenta'     => (float) $invoice->total,
            'details'           => $this->mapDetails($invoice),
        ];

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->post("{$this->baseUrl}/invoice/send", $payload);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Error en la comunicación con APIsPeru',
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json(),
        ];
    }

    /**
     * Mapea el detalle de ítems al formato UBL de APIsPeru.
     */
    private function mapDetails(Invoice $invoice): array
    {
        return $invoice->details->map(function ($detail) {
            return [
                'codProducto'         => (string) $detail->product_id,
                'unidad'              => 'NIU', // NIU = Unidades, ZZ = Servicios
                'descripcion'         => $detail->product_name,
                'cantidad'            => (float) $detail->quantity,
                'montoValorUnitario'  => round($detail->unit_price / 1.18, 2),
                'montoPrecioUnitario' => (float) $detail->unit_price,
                'montoValorVenta'     => round(($detail->quantity * $detail->unit_price) / 1.18, 2),
                'montoBaseIgv'        => round(($detail->quantity * $detail->unit_price) / 1.18, 2),
                'porcentajeIgv'       => 18,
                'igv'                 => (float) $detail->igv,
                'tipAfeIgv'           => '10', // 10 = Gravado - Operación Onerosa
                'totalImpuestos'      => (float) $detail->igv,
            ];
        })->toArray();
    }
}