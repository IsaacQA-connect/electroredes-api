<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Número de comprobante
        $invoiceNumber = $this->invoice_number 
            ?? ($this->relationLoaded('invoice') && $this->invoice 
                ? "{$this->invoice->series}-" . str_pad((string) $this->invoice->number, 8, '0', STR_PAD_LEFT) 
                : null);

        // 2. URLs de descarga garantizadas para Vue (apuntando al controlador de órdenes)
        $hasInvoice = $invoiceNumber || $this->relationLoaded('invoice') && $this->invoice;

        $pdfUrl = $this->pdf_url 
            ?? ($hasInvoice ? url("/api/v1/orders/{$this->id}/download-pdf") : null);

        $xmlUrl = $this->xml_url 
            ?? ($hasInvoice ? url("/api/v1/orders/{$this->id}/download-xml") : null);

        // 3. Determinar tipo de comprobante sin fallos de tipo de dato
        $docType = $this->invoice_type 
            ?? ($this->relationLoaded('invoice') && in_array((string) $this->invoice?->document_type, ['01', '1'], true) ? 'FACTURA' : 'BOLETA');

        // 4. Datos del cliente con fallback seguro
        $customer = $this->relationLoaded('customer') ? $this->customer : null;
        $invoice  = $this->relationLoaded('invoice') ? $this->invoice : null;

        $docNumber = $this->document_number ?? $customer?->document_number ?? $invoice?->client_doc_number;
        $businessName = $this->business_name ?? $customer?->name ?? $invoice?->client_name;

        return [
            'id'              => $this->id,
            'channel'         => $this->channel,
            'status'          => is_object($this->status) ? ($this->status->value ?? $this->status) : $this->status,

            // Atributos directos del Comprobante para Vue
            'invoice_type'    => $docType,
            'invoice_number'  => $invoiceNumber,
            'document_number' => $docNumber,
            'business_name'   => $businessName,
            'pdf_url'         => $pdfUrl,
            'xml_url'         => $xmlUrl,

            'subtotal'        => (float) $this->subtotal,
            'discount'        => (float) $this->discount,
            'total'           => (float) $this->total,
            'order_date'      => $this->order_date,
            'notes'           => $this->notes,

            // Delegación limpia a sub-recursos
            'customer'        => $this->whenLoaded('customer'),
            'user'            => $this->whenLoaded('user'),
            'details'         => OrderDetailResource::collection($this->whenLoaded('details')),
            'payments'        => PaymentResource::collection($this->whenLoaded('payments')),
            'invoice'         => new InvoiceResource($this->whenLoaded('invoice')),

            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}