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
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price']; // Precio con IGV incluido
            $taxType = $item['tax_type'] ?? 'TAXED';  // TAXED, EXONERATED, UNAFFECTED

            $subtotal = $quantity * $unitPrice;

            if ($taxType === 'TAXED') {
                // Desglosar el IGV del precio unitario
                $valueBase = $subtotal / (1 + $igvRate);
                $opTaxed += $valueBase;
            } elseif ($taxType === 'EXONERATED') {
                $opExonerated += $subtotal;
            } else {
                $opUnaffected += $subtotal;
            }
        }

        $totalIgv = $opTaxed * $igvRate;
        $total = $opTaxed + $totalIgv + $opExonerated + $opUnaffected;

        return [
            'op_taxed'      => round($opTaxed, 2),
            'op_exonerated' => round($opExonerated, 2),
            'op_unaffected' => round($opUnaffected, 2),
            'igv'           => round($totalIgv, 2),
            'total'         => round($total, 2),
        ];
    }

    /**
     * DISPARADOR DE FACTURACIÓN: Crea e invoice a partir de un Order pagado.
     */
    public function createInvoiceFromOrder(Order $order): Invoice
    {
        // 1. Evitar duplicidad si la orden ya tiene factura generada
        $existingInvoice = Invoice::where('order_id', $order->id)->first();
        if ($existingInvoice) {
            return $existingInvoice;
        }

        $isFactura = strtoupper($order->invoice_type ?? 'BOLETA') === 'FACTURA';
        $docType = $isFactura ? '01' : '03'; // 01 = Factura, 03 = Boleta
        $series  = $isFactura 
            ? config('services.apisperu.serie_factura', 'F001') 
            : config('services.apisperu.serie_boleta', 'B001');

        // Determinar tipo de documento cliente (6: RUC, 1: DNI, 0: Sin doc)
        $clientDocType = '1';
        if ($isFactura || strlen($order->document_number) === 11) {
            $clientDocType = '6';
        } elseif (!$order->document_number) {
            $clientDocType = '0';
        }

        // Mapear los items de la Orden
        $items = $order->details->map(function ($detail) {
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
            'client_doc_number' => $order->document_number ?? '00000000',
            'client_name'       => $order->business_name ?? $order->customer->name ?? 'CLIENTE VARIOS',
            'client_address'    => $order->customer->address ?? null,
            'op_taxed'          => $totals['op_taxed'],
            'igv'               => $totals['igv'],
            'total'             => $totals['total'],
            'payment_method'    => $order->payments->first()->method ?? 'ONLINE_PAYMENT',
            'items'             => $items,
            'order_id'          => $order->id,
        ];

        // Obtener o asignar usuario del sistema
        $user = $order->user ?? User::whereHas('role', fn($q) => $q->where('name', 'ADMIN'))->first() ?? auth()->user();

        $invoice = $this->createInvoice($payload, $user);

        // Actualizar datos de comprobante en la orden para consulta en Vue
        $invoiceNumber = "{$invoice->series}-" . str_pad($invoice->number, 8, '0', STR_PAD_LEFT);
        $order->update([
            'invoice_number' => $invoiceNumber,
            'pdf_url'        => $invoice->pdf_path ?? null,
            'xml_url'        => $invoice->xml_path ?? null,
        ]);

        return $invoice;
    }

    /**
     * Crea el registro de Invoice, descuenta inventario y emite a SUNAT.
     */
    public function createInvoice(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $documentType = $data['document_type'];
            $paymentMethod = $data['payment_method'] ?? 'CASH';

            // 1. Correlativo atómico
            $number = $this->seriesService->getNextCorrelative($documentType, $data['series']);

            // 2. Cabecera
            $invoice = Invoice::create([
                'user_id'           => $user->id,
                'order_id'          => $data['order_id'] ?? null,
                'document_type'     => $documentType,
                'series'            => $data['series'],
                'number'            => $number,
                'client_doc_type'   => $data['client_doc_type'],
                'client_doc_number' => $data['client_doc_number'],
                'client_name'       => $data['client_name'],
                'client_address'    => $data['client_address'] ?? null,
                'currency'          => 'PEN',
                'op_taxed'          => $data['op_taxed'],
                'igv'               => $data['igv'],
                'total'             => $data['total'],
                'payment_method'    => $paymentMethod,
                'sunat_status'      => $documentType === 'NV' ? 'INTERNAL' : 'PENDING',
            ]);

            // 3. Detalles e Inventario
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

                if (!empty($item['product_id'])) {
                    $this->inventoryService->registerOutflow(
                        productId: $item['product_id'],
                        quantity: $item['quantity'],
                        referenceType: 'INVOICE',
                        referenceId: $invoice->id,
                        description: "Venta con {$invoice->series}-{$number}"
                    );
                }
            }

            // 4. Caja Chica (Solo pagos en efectivo)
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

            // 5. SUNAT vía APIsPeru
            if (in_array($documentType, ['01', '03'])) {
                $this->processSunatEmission($invoice);
            }

            return $invoice->fresh(['details', 'user']);
        });
    }

    private function processSunatEmission(Invoice $invoice): void
    {
        $apisResult = $this->apisPeruService->sendInvoice($invoice->load('details'));

        if (!$apisResult['success']) {
            $invoice->update([
                'sunat_status'      => 'REJECTED',
                'sunat_description' => $apisResult['message'] ?? 'Error de comunicación con SUNAT'
            ]);
            return;
        }

        $resData = $apisResult['data'];
        $isAccepted = $resData['sunatResponse']['success'] ?? false;

        $baseUrl = config('services.apisperu.url', 'https://facturacion.apisperu.com/api/v1');
        $ruc = config('services.apisperu.ruc', config('app.company_ruc'));

        $pdfPath = "{$baseUrl}/invoice/pdf?ruc={$ruc}&tipoDoc={$invoice->document_type}&serie={$invoice->series}&correlativo={$invoice->number}";
        $xmlPath = "{$baseUrl}/invoice/xml?ruc={$ruc}&tipoDoc={$invoice->document_type}&serie={$invoice->series}&correlativo={$invoice->number}";

        $invoice->update([
            'sunat_status'      => $isAccepted ? 'ACCEPTED' : 'REJECTED',
            'sunat_description' => $resData['sunatResponse']['cdrResponse']['description'] ?? 'Procesado por SUNAT',
            'xml_path'          => $xmlPath,
            'pdf_path'          => $pdfPath,
            'cdr_path'          => $resData['cdrZip'] ?? null,
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