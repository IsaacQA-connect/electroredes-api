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
        $this->token   = config('services.apisperu.token', '');
        $this->baseUrl = config('services.apisperu.url', 'https://facturacion.apisperu.com/api/v1');
    }

    /**
     * Construye la estructura UBL 2.1 reutilizable para APIsPeru
     */
    public function buildPayload(Invoice $invoice): array
    {
        $invoice->loadMissing('details');

        $clientDocType   = (string) ($invoice->client_doc_type ?? '0');
        $clientDocNumber = ($clientDocType === '0' || empty($invoice->client_doc_number) || $invoice->client_doc_number === '0')
            ? '00000000'
            : (string) $invoice->client_doc_number;

        return [
            'ublVersion'     => '2.1',
            'tipoOperacion'  => '0101', // Venta Interna
            'tipoDoc'        => str_pad((string) $invoice->document_type, 2, '0', STR_PAD_LEFT),
            'serie'          => $invoice->series,
            'correlativo'    => (string) (int) $invoice->number,
            'fechaEmision'   => $invoice->created_at ? $invoice->created_at->format('Y-m-d\TH:i:sP') : now()->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'formaPago'      => [
                'moneda' => $invoice->currency ?? 'PEN',
                'tipo'   => 'Contado',
            ],
            'tipoMoneda'     => $invoice->currency ?? 'PEN',
            'client'         => [
                'tipoDoc'   => $clientDocType,
                'numDoc'    => $clientDocNumber,
                'rznSocial' => $invoice->client_name ?? 'CLIENTE VARIOS',
                'address'   => [
                    'direccion' => $invoice->client_address ?? 'CIUDAD',
                ],
            ],
            'company'        => [
                'ruc'             => config('services.apisperu.ruc', config('app.company_ruc', '10723512552')),
                'razonSocial'     => config('services.apisperu.razon_social', config('app.company_name', 'HERRERA PAREDEZ EVENZ NOE')),
                'nombreComercial' => config('services.apisperu.nombre_comercial', config('app.company_commercial_name', 'ELECTROREDES')),
                'address'         => [
                    'direccion'    => config('services.apisperu.direccion', 'Asoc. 8 de diciembre Mz-H1 Lt-8 - Moquegua'),
                    'departamento' => config('services.apisperu.departamento', 'MOQUEGUA'),
                    'provincia'    => config('services.apisperu.provincia', 'MARISCAL NIETO'),
                    'distrito'     => config('services.apisperu.distrito', 'SAN ANTONIO'),
                    'ubigeo'       => config('services.apisperu.ubigeo', '180107'),
                ],
            ],
            'mtoOperGravadas' => (float) $invoice->op_taxed,
            'mtoIGV'          => (float) $invoice->igv,
            'totalImpuestos'  => (float) $invoice->igv,
            'valorVenta'      => (float) $invoice->op_taxed,
            'subTotal'        => (float) $invoice->total,
            'mtoImpVenta'     => (float) $invoice->total,
            'details'         => $this->mapDetails($invoice),
        ];
    }

    /**
     * Envía un comprobante (Factura/Boleta) a SUNAT mediante APIsPeru.
     */
    public function sendInvoice(Invoice $invoice): array
    {
        $payload = $this->buildPayload($invoice);

        try {
            $response = Http::withoutVerifying()
                ->withToken($this->token)
                ->acceptJson()
                ->post("{$this->baseUrl}/invoice/send", $payload);

            $resJson = $response->json() ?? [];

            Log::info("Respuesta HTTP {$response->status()} de APIsPeru para Invoice #{$invoice->id}", [
                'payload'  => $payload,
                'response' => $resJson,
            ]);

            if ($response->failed()) {
                $errorMessage = $resJson['error'] 
                    ?? $resJson['message'] 
                    ?? $resJson['description'] 
                    ?? "Error HTTP {$response->status()} devuelto por APIsPeru";

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'errors'  => $resJson['errors'] ?? null
                ];
            }

            $sunatSuccess = $resJson['sunatResponse']['success'] ?? $resJson['success'] ?? false;

            return [
                'success' => $sunatSuccess,
                'data'    => $resJson,
                'message' => $resJson['sunatResponse']['cdrResponse']['description'] ?? $resJson['message'] ?? 'Procesado',
            ];

        } catch (\Exception $e) {
            Log::error("Excepción al conectar con APIsPeru para Invoice #{$invoice->id}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Excepción de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Genera el archivo PDF binario del comprobante
     */
    public function getInvoicePdf(Invoice $invoice)
    {
        return Http::withoutVerifying()
            ->withToken($this->token)
            ->post("{$this->baseUrl}/invoice/pdf", $this->buildPayload($invoice));
    }

    /**
     * Genera el archivo XML firmado del comprobante
     */
    public function getInvoiceXml(Invoice $invoice)
    {
        return Http::withoutVerifying()
            ->withToken($this->token)
            ->post("{$this->baseUrl}/invoice/xml", $this->buildPayload($invoice));
    }

    /**
     * Mapea el detalle de ítems al formato UBL de APIsPeru.
     */
    private function mapDetails(Invoice $invoice): array
    {
        return $invoice->details->map(function ($detail) {
            $unitPrice   = round((float) $detail->unit_price, 2);
            $quantity    = (float) $detail->quantity;
            $totalItem   = round($unitPrice * $quantity, 2);
            
            $valorVenta    = round($totalItem / 1.18, 2);
            $valorUnitario = round($valorVenta / $quantity, 2);
            $igvItem       = round($totalItem - $valorVenta, 2);

            return [
                'codProducto'       => (string) $detail->product_id,
                'unidad'            => $detail->unit_code ?? 'NIU',
                'descripcion'       => $detail->product_name ?? 'Producto',
                'cantidad'          => $quantity,
                'mtoValorUnitario'  => $valorUnitario,
                'mtoPrecioUnitario' => $unitPrice,
                'mtoValorVenta'     => $valorVenta,
                'mtoBaseIgv'        => $valorVenta,
                'porcentajeIgv'     => 18,
                'igv'               => $igvItem,
                'tipAfeIgv'         => '10',
                'totalImpuestos'    => $igvItem,
            ];
        })->toArray();
    }
}