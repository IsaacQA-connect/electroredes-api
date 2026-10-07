<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PrintService
{
    /**
     * Genera el PDF del Ticket Térmico de 80mm.
     */
    public function generateTicketPdf(Invoice $invoice)
    {
        $invoice->load(['details', 'user']);

        $qrBase64 = $this->generateSunatQrBase64($invoice);

        // Configurar papel térmico de 80mm (Ancho: 226.77 pt (~80mm), Alto dinámico)
        $pdf = Pdf::loadView('pdf.ticket_80mm', [
            'invoice' => $invoice,
            'qrBase64' => $qrBase64,
            'company' => $this->getCompanyInfo(),
        ])->setPaper([0, 0, 226.77, 600], 'portrait');

        return $pdf->stream("ticket_{$invoice->series}-{$invoice->number}.pdf");
    }

    /**
     * Genera el PDF en formato formal A4.
     */
    public function generateA4Pdf(Invoice $invoice)
    {
        $invoice->load(['details', 'user']);

        $qrBase64 = $this->generateSunatQrBase64($invoice);

        $pdf = Pdf::loadView('pdf.invoice_a4', [
            'invoice' => $invoice,
            'qrBase64' => $qrBase64,
            'company' => $this->getCompanyInfo(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("comprobante_{$invoice->series}-{$invoice->number}.pdf");
    }

    /**
     * Construye la cadena estándar SUNAT y genera la imagen del código QR en Base64.
     * Formato SUNAT: RUC | TIPO_DOC | SERIE | NUMERO | IGV | TOTAL | FECHA | TIPO_DOC_CLI | NUM_DOC_CLI
     */
    private function generateSunatQrBase64(Invoice $invoice): string
    {
        $companyRuc = config('app.company_ruc', '20000000000');

        $qrText = implode('|', [
            $companyRuc,
            $invoice->document_type,
            $invoice->series,
            $invoice->number,
            number_format($invoice->igv, 2, '.', ''),
            number_format($invoice->total, 2, '.', ''),
            $invoice->created_at->format('Y-m-d'),
            $invoice->client_doc_type,
            $invoice->client_doc_number,
        ]);

        // Si la librería simplesoftwareio/simple-qrcode está instalada
        if (class_exists(QrCode::class)) {
            $png = QrCode::format('png')->size(120)->margin(1)->generate($qrText);
            return 'data:image/png;base64,' . base64_encode($png);
        }

        // Fallback usando API pública de código QR
        return 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($qrText);
    }

    private function getCompanyInfo(): array
    {
        return [
            'ruc'          => config('app.company_ruc', '20000000000'),
            'name'         => config('app.company_name', 'MI EMPRESA S.A.C.'),
            'trade_name'   => config('app.company_commercial_name', 'MI EMPRESA'),
            'address'      => config('app.company_address', 'AV. PRINCIPAL 123 - MOQUEGUA'),
            'phone'        => config('app.company_phone', '987654321'),
            'email'        => config('app.company_email', 'ventas@miempresa.com'),
        ];
    }
}