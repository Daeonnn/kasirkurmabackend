<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixSalesUserSequenceConstraint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            // Hapus constraint lama yang hanya mempertimbangkan user_id dan transaction_sequence
            $table->dropUnique('sales_user_sequence_unique');

            // Tambahkan constraint baru yang mempertimbangkan user_id, date, dan transaction_sequence
            $table->unique(['user_id', 'date', 'transaction_sequence'], 'sales_user_date_sequence_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            // Hapus constraint baru
            $table->dropUnique('sales_user_date_sequence_unique');
            
            // Kembalikan constraint lama
            $table->unique(['user_id', 'transaction_sequence'], 'sales_user_sequence_unique');
        });
    }
}