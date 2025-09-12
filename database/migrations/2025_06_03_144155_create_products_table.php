<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang')->unique();
            $table->string('name');
            $table->string('photo')->nullable(); // ← SUDAH ADA DI SINI

            // Foreign keys dengan RESTRICT
            $table->foreignId('jenis_id')
                  ->constrained('jenis')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            $table->foreignId('satuan_id')
                  ->constrained('satuan')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            $table->foreignId('distributor_id')
                  ->constrained('distributors')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();

            $table->integer('stock')->default(0);
            $table->decimal('selling_price', 15, 2);

            $table->timestamps();
            // Hard delete - no softDeletes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
