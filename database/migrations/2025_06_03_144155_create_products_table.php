<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang')->unique();
            $table->string('name');
            $table->foreignId('jenis_id')->constrained('jenis')->onDelete('cascade');
            $table->foreignId('satuan_id')->constrained('satuan')->onDelete('cascade');
            $table->foreignId('distributor_id')->constrained('distributors')->onDelete('cascade');
            $table->integer('stock')->default(0);
            $table->decimal('selling_price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};

