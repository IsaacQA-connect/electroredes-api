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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 20)->nullable();

            $table->string('business_name', 150);
            $table->string('contact_name', 150)->nullable();

            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();

            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique([
                'document_type',
                'document_number'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
