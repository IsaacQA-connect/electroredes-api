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
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 2); // '01' = Factura, '03' = Boleta
            $table->string('series', 4);        // Ej: 'F001', 'B001'
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'series']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
