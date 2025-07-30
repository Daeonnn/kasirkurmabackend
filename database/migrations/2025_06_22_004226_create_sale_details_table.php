<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✅ Migration untuk tabel sale_details
     */
    public function up(): void
    {
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();

            // ✅ RELATION TO SALE
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');

            // ✅ RELATION TO PRODUCT - restrict agar produk tidak bisa dihapus jika ada transaksi
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');

            // ✅ TRANSACTION DETAIL DATA
            $table->integer('quantity');
            $table->decimal('selling_price', 15, 2); // Harga saat transaksi (bisa beda dari harga produk)
            $table->decimal('subtotal', 15, 2);

            // ✅ TIMESTAMPS
            $table->timestamps();

            // ✅ INDEXES untuk performa
            $table->index('sale_id');
            $table->index('product_id');
            $table->index(['sale_id', 'product_id']); // Composite index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};
