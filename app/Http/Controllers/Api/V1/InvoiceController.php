<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use App\Services\PrintService;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected PrintService $printService
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
     * Descarga del archivo XML firmado.
     */
    public function downloadXml(Invoice $invoice)
    {
        if (!$invoice->xml_path || !Storage::exists($invoice->xml_path)) {
            return response()->json(['message' => 'Archivo XML no encontrado'], 404);
        }

        return Storage::download($invoice->xml_path);
    }

    /**
     * Descarga de la Constancia de Recepción (CDR Zip de SUNAT).
     */
    public function downloadCdr(Invoice $invoice)
    {
        if (!$invoice->cdr_path || !Storage::exists($invoice->cdr_path)) {
            return response()->json(['message' => 'Archivo CDR no encontrado'], 404);
        }

        return Storage::download($invoice->cdr_path);
    }

    /**
     * Transmite el PDF del Ticket Térmico (80mm)
     */
    public function streamTicket(Invoice $invoice)
    {
        return $this->printService->generateTicketPdf($invoice);
    }

    /**
     * Transmite el PDF en formato A4
     */
    public function streamPdf(Invoice $invoice)
    {
        return $this->printService->generateA4Pdf($invoice);
    }

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