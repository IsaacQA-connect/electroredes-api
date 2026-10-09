<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained(); // Si proviene de una venta
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Usuario que emite
            
            // Tipo de documento (01: Factura, 03: Boleta, 07: Nota Crédito, 08: Nota Débito)
            $table->string('document_type', 2); 
            $table->string('series', 4);        // Ej: F001, B001
            $table->unsignedBigInteger('number'); // Ej: 1, 2, 3...
            
            // Cliente al momento de emisión
            $table->string('client_doc_type', 2); // 1: DNI, 6: RUC
            $table->string('client_doc_number', 15);
            $table->string('client_name');
            $table->string('client_address')->nullable();
            
            // Totales y Moneda
            $table->string('currency', 3)->default('PEN');
            $table->decimal('op_taxed', 12, 2)->default(0);    // Gravado
            $table->decimal('op_exonerated', 12, 2)->default(0); // Exonerado
            $table->decimal('op_unaffected', 12, 2)->default(0);  // Inafecto
            $table->decimal('igv', 12, 2)->default(0);           // 18%
            $table->decimal('total', 12, 2)->default(0);
            
            // Estado SUNAT
            $table->enum('sunat_status', ['PENDING', 'ACCEPTED', 'REJECTED', 'ANNULLED'])->default('PENDING');
            $table->string('sunat_response_code')->nullable();
            $table->text('sunat_description')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->timestamps();

            // Unicidad por Serie y Correlativo
            $table->unique(['document_type', 'series', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
