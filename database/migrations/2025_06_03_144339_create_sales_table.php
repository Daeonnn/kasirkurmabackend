<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique()->index();
            $table->date('date');
            $table->decimal('total_price', 15, 2);
            $table->enum('payment_method', ['tunai', 'qris'])->default('tunai');
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->index('date');
            $table->index('user_id');
            $table->index('payment_method');
            $table->index(['date', 'user_id']);
            $table->index(['date', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
