<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✅ TAMBAH transaction_sequence untuk sistem per-user transaction code
     * Migration ini untuk menambahkan kolom yang dibutuhkan ke tabel sales yang sudah ada
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // ✅ TAMBAH kolom transaction_sequence setelah transaction_code
            if (!Schema::hasColumn('sales', 'transaction_sequence')) {
                $table->integer('transaction_sequence')->after('transaction_code')->index();
            }

            // ✅ HAPUS unique constraint global dari transaction_code
            try {
                $table->dropUnique(['transaction_code']);
            } catch (\Exception $e) {
                // Ignore jika constraint tidak ada atau sudah dihapus
            }

            // ✅ TAMBAH unique constraints per-user
            try {
                $table->unique(['user_id', 'transaction_code'], 'sales_user_transaction_unique');
                $table->unique(['user_id', 'transaction_sequence'], 'sales_user_sequence_unique');
            } catch (\Exception $e) {
                // Ignore jika sudah ada
            }

            // ✅ TAMBAH composite index untuk performa
            $table->index(['user_id', 'transaction_sequence'], 'sales_user_sequence_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // ✅ HAPUS constraints dan indexes yang ditambahkan
            try {
                $table->dropUnique('sales_user_transaction_unique');
                $table->dropUnique('sales_user_sequence_unique');
                $table->dropIndex('sales_user_sequence_index');
            } catch (\Exception $e) {
                // Ignore errors
            }

            // ✅ HAPUS kolom transaction_sequence
            if (Schema::hasColumn('sales', 'transaction_sequence')) {
                $table->dropColumn('transaction_sequence');
            }

            // ✅ KEMBALIKAN unique constraint global (opsional)
            try {
                $table->unique('transaction_code');
            } catch (\Exception $e) {
                // Ignore jika error
            }
        });
    }
};
