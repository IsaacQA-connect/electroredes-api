<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Datos formateados del comprobante si existe o tiene relación
        $invoiceNumber = $this->invoice_number 
            ?? ($this->invoice ? "{$this->invoice->series}-" . str_pad($this->invoice->number, 8, '0', STR_PAD_LEFT) : null);

        $pdfUrl = $this->pdf_url 
            ?? ($this->invoice ? url("/api/invoices/{$this->invoice->id}/pdf") : null);

        $xmlUrl = $this->xml_url 
            ?? ($this->invoice ? url("/api/invoices/{$this->invoice->id}/xml") : null);

        return [
            'id'              => $this->id,
            'channel'         => $this->channel,
            'status'          => is_object($this->status) ? $this->status->value : $this->status,

            // Atributos directos del Comprobante para Vue
            'invoice_type'    => $this->invoice_type ?? ($this->invoice?->document_type === '01' ? 'FACTURA' : 'BOLETA'),
            'invoice_number'  => $invoiceNumber,
            'document_number' => $this->document_number ?? $this->customer?->document_number ?? $this->invoice?->client_doc_number,
            'business_name'   => $this->business_name ?? $this->customer?->name ?? $this->invoice?->client_name,
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

            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}