<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✅ FIXED: Migration untuk tabel sales dengan sistem transaction_code yang benar
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            // ✅ TRANSACTION CODE - Primary identifier untuk struk
            $table->string('transaction_code')->unique()->index();

            // ✅ TRANSACTION DATA
            $table->date('date');
            $table->decimal('total_price', 15, 2);

            // ✅ PAYMENT INFO
            $table->enum('payment_method', ['tunai', 'qris'])->default('tunai');
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();

            // ✅ RELATION TO USER (Kasir yang melakukan transaksi)
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            // ✅ TIMESTAMPS
            $table->timestamps();

            // ✅ INDEXES untuk performa query
            $table->index('date');
            $table->index('user_id');
            $table->index('payment_method');
            $table->index(['date', 'user_id']); // Composite index untuk laporan per kasir per hari
            $table->index(['date', 'payment_method']); // Composite index untuk laporan per metode pembayaran
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
