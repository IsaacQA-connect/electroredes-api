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
        Schema::create('order_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')
                ->constrained('order_returns')
                ->cascadeOnDelete();

            $table->foreignId('order_detail_id')
                ->constrained('order_details')
                ->restrictOnDelete();

            $table->decimal('quantity', 12, 2);

            $table->decimal('unit_price', 12, 2);

            $table->decimal('subtotal', 12, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_return_details');
    }
};
