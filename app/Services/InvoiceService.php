<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterMovement;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(
        protected SeriesService $seriesService,
        protected InventoryService $inventoryService,
        protected ApisPeruService $apisPeruService
    ) {}

    /**
     * Calcula Subtotal, IGV y Total de una lista de ítems.
     */
    public function calculateTotals(array $items, float $igvRate = 0.18): array
    {
        $opTaxed = 0;
        $opExonerated = 0;
        $opUnaffected = 0;

        foreach ($items as $item) {
            $quantity  = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price']; // Precio con IGV incluido
            $taxType   = $item['tax_type'] ?? 'TAXED';  // TAXED, EXONERATED, UNAFFECTED

            $subtotal = $quantity * $unitPrice;

            if ($taxType === 'TAXED') {
                $valueBase = $subtotal / (1 + $igvRate);
                $opTaxed += $valueBase;
            } elseif ($taxType === 'EXONERATED') {
                $opExonerated += $subtotal;
            } else {
                $opUnaffected += $subtotal;
            }
        }

        $totalIgv = $opTaxed * $igvRate;
        $total    = $opTaxed + $totalIgv + $opExonerated + $opUnaffected;

        return [
            'op_taxed'      => round($opTaxed, 2),
            'op_exonerated' => round($opExonerated, 2),
            'op_unaffected' => round($opUnaffected, 2),
            'igv'           => round($totalIgv, 2),
            'total'         => round($total, 2),
        ];
    }

    /**
     * DISPARADOR DE FACTURACIÓN: Crea un invoice a partir de un Order pagado.
     */
    public function createInvoiceFromOrder(Order $order): Invoice
    {
        // 🔒 BLOQUEO PESIMISTA: Evita concurrencia entre Webhook y Frontend
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // 1. Evitar duplicidad si la orden ya tiene factura generada
            $existingInvoice = Invoice::where('order_id', $lockedOrder->id)->first();
            if ($existingInvoice) {
                return $existingInvoice;
            }

            $isFactura = strtoupper($lockedOrder->invoice_type ?? 'BOLETA') === 'FACTURA';
            $docType   = $isFactura ? '01' : '03'; // 01 = Factura, 03 = Boleta
            $series    = $isFactura 
                ? config('services.apisperu.serie_factura', 'F001') 
                : config('services.apisperu.serie_boleta', 'B001');

            // Determinar tipo de documento cliente (6: RUC, 1: DNI, 0: Sin doc)
            $clientDocType = '1';
            if ($isFactura || strlen($lockedOrder->document_number ?? '') === 11) {
                $clientDocType = '6';
            } elseif (!$lockedOrder->document_number) {
                $clientDocType = '0';
            }

            // Fallback seguro de dirección
            $clientAddress = $lockedOrder->shipping_address 
                ?? $lockedOrder->notes 
                ?? $lockedOrder->customer?->address 
                ?? 'CIUDAD';

            // Mapear los items de la Orden
            $items = $lockedOrder->details->map(function ($detail) {
                return [
                    'product_id'   => $detail->product_id,
                    'product_name' => $detail->product->name ?? 'Producto',
                    'quantity'     => $detail->quantity,
                    'unit_price'   => $detail->unit_price,
                    'tax_type'     => 'TAXED'
                ];
            })->toArray();

            $totals = $this->calculateTotals($items);

            $payload = [
                'document_type'     => $docType,
                'series'            => $series,
                'client_doc_type'   => $clientDocType,
                'client_doc_number' => $lockedOrder->document_number ?? '00000000',
                'client_name'       => $lockedOrder->business_name ?? $lockedOrder->customer?->name ?? 'CLIENTE VARIOS',
                'client_address'    => $clientAddress,
                'op_taxed'          => $totals['op_taxed'],
                'igv'               => $totals['igv'],
                'total'             => $totals['total'],
                'payment_method'    => $lockedOrder->payments->first()->method ?? 'ONLINE_PAYMENT',
                'items'             => $items,
                'order_id'          => $lockedOrder->id,
                'customer_id'       => $lockedOrder->customer_id ?? null,
            ];

            // Obtener o asignar usuario del sistema
            $user = $lockedOrder->user 
                ?? auth()->user() 
                ?? User::first();

            $invoice = $this->createInvoice($payload, $user);

            // Actualizar datos de comprobante en la orden con URLs proxy garantizadas
            $invoiceNumber = "{$invoice->series}-" . str_pad((string) $invoice->number, 8, '0', STR_PAD_LEFT);

            $lockedOrder->update([
                'invoice_number' => $invoiceNumber,
                'pdf_url'        => url("/api/invoices/{$invoice->id}/pdf"),
                'xml_url'        => url("/api/invoices/{$invoice->id}/xml"),
            ]);

            return $invoice;
        });
    }

    /**
     * Crea el registro de Invoice, descuenta inventario y emite a SUNAT.
     */
    public function createInvoice(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $documentType  = $data['document_type'];
            $paymentMethod = $data['payment_method'] ?? 'CASH';

            // 1. Correlativo atómico
            $number = $this->seriesService->getNextCorrelative($documentType, $data['series']);

            // 2. Cabecera de Factura
            $invoice = Invoice::create([
                'user_id'           => $user->id,
                'order_id'          => $data['order_id'] ?? null,
                'customer_id'       => $data['customer_id'] ?? null,
                'document_type'     => $documentType,
                'series'            => $data['series'],
                'number'            => $number,
                'client_doc_type'   => $data['client_doc_type'],
                'client_doc_number' => $data['client_doc_number'],
                'client_name'       => $data['client_name'],
                'client_address'    => $data['client_address'] ?? 'CIUDAD',
                'currency'          => 'PEN',
                'op_taxed'          => $data['op_taxed'],
                'igv'               => $data['igv'],
                'total'             => $data['total'],
                'payment_method'    => $paymentMethod,
                'sunat_status'      => $documentType === 'NV' ? 'INTERNAL' : 'PENDING',
            ]);

            // 3. Detalles del comprobante
            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $itemIgv  = $subtotal - ($subtotal / 1.18);

                InvoiceDetail::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'igv'          => round($itemIgv, 2),
                    'total'        => round($subtotal, 2),
                ]);
            }

            // 4. Descontar Inventario
            if (!empty($data['order_id'])) {
                $order = Order::with('details')->find($data['order_id']);
                if ($order) {
                    $this->inventoryService->registerSaleExit($order, $user);
                }
            }

            // 5. Caja Chica (Solo pagos en efectivo)
            $activeRegister = CashRegister::where('status', 'OPEN')->latest()->first();
            if ($activeRegister && $paymentMethod === 'CASH') {
                CashRegisterMovement::create([
                    'cash_register_id' => $activeRegister->id,
                    'user_id'          => $user->id,
                    'type'             => 'INFLOW',
                    'amount'           => $invoice->total,
                    'description'      => "Venta {$invoice->series}-{$number} ({$invoice->client_name})",
                    'reference_type'   => 'INVOICE',
                    'reference_id'     => $invoice->id,
                ]);
            }

            // 6. SUNAT vía APIsPeru
            if (in_array($documentType, ['01', '03'])) {
                $this->processSunatEmission($invoice);
            }

            return $invoice->fresh(['details', 'user']);
        });
    }

    private function processSunatEmission(Invoice $invoice): void
    {
        $pdfUrl = url("/api/invoices/{$invoice->id}/pdf");
        $xmlUrl = url("/api/invoices/{$invoice->id}/xml");
        $cdrUrl = url("/api/invoices/{$invoice->id}/cdr");

        $apisResult = $this->apisPeruService->sendInvoice($invoice->load('details'));

        if (!$apisResult['success']) {
            $invoice->update([
                'sunat_status'      => 'REJECTED',
                'sunat_description' => $apisResult['message'] ?? 'Error de comunicación con SUNAT',
                'pdf_url'           => $pdfUrl,
                'pdf_path'          => $pdfUrl,
                'xml_url'           => $xmlUrl,
                'xml_path'          => $xmlUrl,
            ]);
            return;
        }

        $resData = $apisResult['data'] ?? [];

        $responseCode = isset($resData['sunatResponse']['cdrResponse']['code'])
            ? (string) $resData['sunatResponse']['cdrResponse']['code']
            : (isset($resData['code']) ? (string) $resData['code'] : '0');

        $description = $resData['sunatResponse']['cdrResponse']['description'] 
            ?? $resData['sunatResponse']['description'] 
            ?? $apisResult['message'] 
            ?? 'Procesado por SUNAT';

        // Guardar archivo ZIP CDR localmente
        $cdrRawBase64 = $resData['sunatResponse']['cdrZip'] ?? $resData['cdrZip'] ?? null;
        $localCdrPath = null;

        if ($cdrRawBase64) {
            $fileName = "cdr_{$invoice->series}_{$invoice->number}.zip";
            Storage::disk('local')->put("invoices/cdr/{$fileName}", base64_decode($cdrRawBase64));
            $localCdrPath = "invoices/cdr/{$fileName}";
        }

        $invoice->update([
            'sunat_status'        => 'ACCEPTED',
            'sunat_description'   => $description,
            'sunat_response_code' => $responseCode,
            'xml_url'             => $xmlUrl,
            'xml_path'            => $xmlUrl,
            'pdf_url'             => $pdfUrl,
            'pdf_path'            => $pdfUrl,
            'cdr_url'             => $cdrUrl,
            'cdr_path'            => $localCdrPath ?? $cdrUrl,
        ]);
    }

    public function resendToSunat(Invoice $invoice): Invoice
    {
        if ($invoice->document_type === 'NV') {
            throw new \Exception('Las Notas de Venta son de uso interno y no se envían a SUNAT.');
        }

        if ($invoice->sunat_status === 'ACCEPTED') {
            throw new \Exception('El comprobante ya fue aceptado previamente por SUNAT.');
        }

        $this->processSunatEmission($invoice);

        return $invoice->fresh();
    }
}