<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Product reference
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            // Simple movement types
            $table->enum('type', ['in', 'out']); // Masuk atau Keluar
            $table->integer('quantity'); // Jumlah (selalu positif)

            // Distributor info (hanya untuk stock in)
            $table->foreignId('distributor_id')->nullable()
                  ->constrained('distributors')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            // Basic tracking info
            $table->text('notes')->nullable(); // Catatan
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            $table->timestamps();

            // Indexes untuk performance
            $table->index(['product_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
