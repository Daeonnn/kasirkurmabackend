<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 15, 2)->nullable()->after('total_price');
            $table->decimal('discount_amount', 15, 2)->nullable()->after('subtotal_amount');
            $table->string('discount_type')->nullable()->after('discount_amount'); // 'percentage' atau 'fixed'
            $table->decimal('discount_value', 15, 2)->nullable()->after('discount_type');
            $table->index('discount_type');
            $table->index(['discount_type', 'discount_amount']); // Composite index untuk laporan diskon
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['discount_type']);
            $table->dropIndex(['discount_type', 'discount_amount']);
            $table->dropColumn([
                'subtotal_amount',
                'discount_amount',
                'discount_type',
                'discount_value'
            ]);
        });
    }
};
