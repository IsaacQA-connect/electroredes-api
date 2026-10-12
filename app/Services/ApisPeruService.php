<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApisPeruService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token   = config('services.apisperu.token');
        $this->baseUrl = config('services.apisperu.url', 'https://facturacion.apisperu.com/api/v1');
    }

    /**
     * Envía un comprobante (Factura/Boleta) a SUNAT mediante APIsPeru.
     */
    public function sendInvoice(Invoice $invoice): array
    {
        $payload = [
            'ublVersion'        => '2.1',
            'tipoDoc'           => $invoice->document_type, // '01' Factura, '03' Boleta
            'serie'             => $invoice->series,        // Ej: 'F001' o 'B001'
            'correlativo'       => (string) $invoice->number,
            'fechaEmision'      => $invoice->created_at ? $invoice->created_at->format('Y-m-d\TH:i:sP') : now()->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'formaPago'         => [
                'moneda' => $invoice->currency ?? 'PEN',
                'tipo'   => 'Contado',
            ],
            'tipoMoneda'        => $invoice->currency ?? 'PEN',
            'client'            => [
                'tipoDoc'   => $invoice->client_doc_type, // '6' RUC, '1' DNI, '0' Sin Doc
                'numDoc'    => $invoice->client_doc_number ?? '00000000',
                'rznSocial' => $invoice->client_name,
                'address'   => [
                    'direccion' => $invoice->client_address ?? 'CIUDAD',
                ],
            ],
            'company'           => [
                'ruc'             => config('services.apisperu.ruc', config('app.company_ruc', '20000000000')),
                'razonSocial'     => config('services.apisperu.razon_social', config('app.company_name', 'ELECTRO REDES S.A.C.')),
                'nombreComercial' => config('services.apisperu.nombre_comercial', config('app.company_commercial_name', 'ELECTRO REDES')),
                'address'         => [
                    'direccion'    => config('services.apisperu.direccion', 'AV. PRINCIPAL 123'),
                    'departamento' => config('services.apisperu.departamento', 'MOQUEGUA'),
                    'provincia'    => config('services.apisperu.provincia', 'MARISCAL NIETO'),
                    'distrito'     => config('services.apisperu.distrito', 'MOQUEGUA'),
                    'ubigeo'       => config('services.apisperu.ubigeo', '180101'),
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

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->post("{$this->baseUrl}/invoice/send", $payload);

            if ($response->failed()) {
                Log::error('Error APIsPeru HTTP Status ' . $response->status(), [
                    'response' => $response->json(),
                    'invoice'  => $invoice->id
                ]);

                return [
                    'success' => false,
                    'message' => $response->json('message') ?? $response->json('description') ?? 'Error en la comunicación con APIsPeru',
                    'errors'  => $response->json('errors') ?? null
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Excepción al conectar con APIsPeru: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Excepción de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mapea el detalle de ítems al formato UBL de APIsPeru.
     */
    private function mapDetails(Invoice $invoice): array
    {
        return $invoice->details->map(function ($detail) {
            $unitPrice    = (float) $detail->unit_price;
            $quantity     = (float) $detail->quantity;
            $totalItem    = $unitPrice * $quantity;
            $valorVenta   = round($totalItem / 1.18, 2);

            return [
                'codProducto'         => (string) $detail->product_id,
                'unidad'              => $detail->unit_code ?? 'NIU', // NIU = Unidades, ZZ = Servicios
                'descripcion'         => $detail->product_name,
                'cantidad'            => $quantity,
                'montoValorUnitario'  => round($unitPrice / 1.18, 4),
                'montoPrecioUnitario' => $unitPrice,
                'montoValorVenta'     => $valorVenta,
                'montoBaseIgv'        => $valorVenta,
                'porcentajeIgv'       => 18,
                'igv'                 => (float) $detail->igv,
                'tipAfeIgv'           => '10', // 10 = Gravado - Operación Onerosa
                'totalImpuestos'      => (float) $detail->igv,
            ];
        })->toArray();
    }
}