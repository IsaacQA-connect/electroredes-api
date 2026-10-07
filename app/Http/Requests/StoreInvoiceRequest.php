<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type'     => ['required', 'string', 'in:01,03,NV'],
            'series'            => ['required', 'string', 'max:4'],
            'client_doc_type'   => ['required', 'string'],
            'client_doc_number' => ['required', 'string', 'max:15'],
            'client_name'       => ['required', 'string', 'max:255'],
            'client_address'    => ['nullable', 'string', 'max:500'],
            'payment_method'    => ['nullable', 'string', 'in:CASH,YAPE,PLIN,CARD,TRANSFER'],
            'op_taxed'          => ['required', 'numeric', 'min:0'],
            'igv'               => ['required', 'numeric', 'min:0'],
            'total'             => ['required', 'numeric', 'min:0.01'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'=> ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity'  => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'=> ['required', 'numeric', 'min:0'],
            'items.*.tax_type'  => ['nullable', 'string', 'in:TAXED,EXONERATED,UNAFFECTED'],
        ];
    }
}