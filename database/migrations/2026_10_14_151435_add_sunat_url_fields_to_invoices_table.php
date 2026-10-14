<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'pdf_path')) {
                $table->text('pdf_path')->nullable()->after('xml_path');
            }
            if (!Schema::hasColumn('invoices', 'pdf_url')) {
                $table->text('pdf_url')->nullable()->after('pdf_path');
            }
            if (!Schema::hasColumn('invoices', 'xml_url')) {
                $table->text('xml_url')->nullable()->after('xml_path');
            }
            if (!Schema::hasColumn('invoices', 'cdr_url')) {
                $table->text('cdr_url')->nullable()->after('cdr_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('invoices', 'pdf_path') ? 'pdf_path' : null,
                Schema::hasColumn('invoices', 'pdf_url') ? 'pdf_url' : null,
                Schema::hasColumn('invoices', 'xml_url') ? 'xml_url' : null,
                Schema::hasColumn('invoices', 'cdr_url') ? 'cdr_url' : null,
            ]));
        });
    }
};