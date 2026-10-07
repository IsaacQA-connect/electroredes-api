<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'document_type'     => $this->document_type,
            'document_type_label'=> match($this->document_type) {
                '01' => 'Factura Electrónica',
                '03' => 'Boleta de Venta Electrónica',
                'NV' => 'Nota de Venta',
                default => 'Comprobante'
            },
            'full_number'       => "{$this->series}-" . str_pad((string) $this->number, 8, '0', STR_PAD_LEFT),
            'series'            => $this->series,
            'number'            => $this->number,
            'client' => [
                'doc_type'   => $this->client_doc_type,
                'doc_number' => $this->client_doc_number,
                'name'       => $this->client_name,
                'address'    => $this->client_address,
            ],
            'currency'          => $this->currency,
            'op_taxed'          => (float) $this->op_taxed,
            'igv'               => (float) $this->igv,
            'total'             => (float) $this->total,
            'payment_method'    => $this->payment_method,
            'sunat_status'      => $this->sunat_status,
            'sunat_description' => $this->sunat_description,
            'links' => [
                'pdf'    => url("/api/invoices/{$this->id}/pdf"),
                'ticket' => url("/api/invoices/{$this->id}/ticket"),
                'xml'    => $this->xml_path,
                'cdr'    => $this->cdr_path,
            ],
            'details'           => InvoiceDetailResource::collection($this->whenLoaded('details')),
            'issuer'            => $this->whenLoaded('user', fn () => $this->user->name),
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}