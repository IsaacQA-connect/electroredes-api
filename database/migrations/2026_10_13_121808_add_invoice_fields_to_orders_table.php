<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 20)->nullable()->after('total');
            $table->text('pdf_url')->nullable()->after('invoice_number');
            $table->text('xml_url')->nullable()->after('pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'pdf_url', 'xml_url']);
        });
    }
};