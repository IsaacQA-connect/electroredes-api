<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\CashRegister;
use App\Models\CashRegisterMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    public function createInvoice(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $documentType = $data['document_type'];
            $paymentMethod = $data['payment_method'] ?? 'CASH';

            // 1. Correlativo correlacionado de forma atómica
            $number = $this->seriesService->getNextCorrelative($documentType, $data['series']);

            // 2. Cabecera
            $invoice = Invoice::create([
                'user_id'           => $user->id,
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
                $itemIgv = $subtotal - ($subtotal / 1.18);

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

            // 4. Caja Chica
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

            // 5. SUNAT via APIsPeru
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

        $invoice->update([
            'sunat_status'      => $isAccepted ? 'ACCEPTED' : 'REJECTED',
            'sunat_description' => $resData['sunatResponse']['cdrResponse']['description'] ?? 'Procesado por SUNAT',
            'xml_path'          => $resData['xml'] ?? null,
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