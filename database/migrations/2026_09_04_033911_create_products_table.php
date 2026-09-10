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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('code', 50)->unique();
            $table->string('barcode', 100)->nullable()->unique();

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->string('unit', 20)->default('UND');

            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2);

            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('minimum_stock', 10, 2)->default(0);

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
