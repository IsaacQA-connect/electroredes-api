<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\ApisPeruService;
use App\Services\InvoiceService;
use App\Services\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected PrintService $printService,
        protected ApisPeruService $apisPeruService
    ) {}

    /**
     * Historial de comprobantes emitidos.
     */
    public function index(): AnonymousResourceCollection
    {
        $invoices = Invoice::with(['user', 'details'])
            ->latest()
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Emisión de Boletas, Facturas y Notas de Venta.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->createInvoice(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => $invoice->document_type === 'NV'
                ? 'Nota de Venta registrada correctamente'
                : 'Comprobante emitido correctamente',
            'data' => new InvoiceResource($invoice)
        ], 201);
    }

    /**
     * Ver detalle de un comprobante específico.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['details', 'user']));
    }

    /**
     * Descarga del archivo PDF del Comprobante (Generado vía APIsPeru).
     */
    public function downloadPdf(Invoice $invoice)
    {
        $response = $this->apisPeruService->getInvoicePdf($invoice->loadMissing('details'));

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo generar el PDF en APIsPeru.',
                'error'   => $response->json() ?? $response->body()
            ], $response->status());
        }

        return response($response->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$invoice->series}-{$invoice->number}.pdf\"",
        ]);
    }

    /**
     * Descarga del archivo XML firmado (Generado vía APIsPeru).
     */
    public function downloadXml(Invoice $invoice)
    {
        // 1. Si existe un archivo XML físico en el servidor local
        if ($invoice->xml_path && !str_starts_with($invoice->xml_path, 'http') && Storage::exists($invoice->xml_path)) {
            return Storage::download($invoice->xml_path);
        }

        // 2. Si es una URL o no existe localmente, generar/descargar desde APIsPeru
        $response = $this->apisPeruService->getInvoiceXml($invoice->loadMissing('details'));

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo generar el XML en APIsPeru.',
                'error'   => $response->json() ?? $response->body()
            ], $response->status());
        }

        return response($response->body(), 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$invoice->series}-{$invoice->number}.xml\"",
        ]);
    }

    /**
     * Descarga de la Constancia de Recepción (CDR Zip de SUNAT guardado en disco local).
     */
    public function downloadCdr(Invoice $invoice)
    {
        if ($invoice->cdr_path && Storage::disk('local')->exists($invoice->cdr_path)) {
            return Storage::disk('local')->download($invoice->cdr_path);
        }

        if ($invoice->cdr_path && Storage::exists($invoice->cdr_path)) {
            return Storage::download($invoice->cdr_path);
        }

        return response()->json(['message' => 'Archivo CDR no encontrado en el servidor.'], 404);
    }

    /**
     * Transmite el PDF del Ticket Térmico (80mm).
     */
    public function streamTicket(Invoice $invoice)
    {
        return $this->printService->generateTicketPdf($invoice);
    }

    /**
     * Transmite el PDF en formato A4.
     */
    public function streamPdf(Invoice $invoice)
    {
        return $this->printService->generateA4Pdf($invoice);
    }

    /**
     * Reintento de envío a SUNAT.
     */
    public function resendSunat(Invoice $invoice): JsonResponse
    {
        try {
            $updatedInvoice = $this->invoiceService->resendToSunat($invoice);

            return response()->json([
                'message' => $updatedInvoice->sunat_status === 'ACCEPTED' 
                    ? 'Comprobante aceptado por SUNAT correctamente.' 
                    : 'Reintento ejecutado. Respuesta: ' . $updatedInvoice->sunat_description,
                'data' => new InvoiceResource($updatedInvoice)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}