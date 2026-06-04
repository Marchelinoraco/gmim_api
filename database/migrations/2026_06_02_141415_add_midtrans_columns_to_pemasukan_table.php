<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemasukan', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->unique()->after('rejected_reason');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('payment_type')->nullable()->after('midtrans_transaction_id');
            $table->timestamp('settled_at')->nullable()->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('pemasukan', function (Blueprint $table) {
            $table->dropColumn(['midtrans_order_id', 'midtrans_transaction_id', 'payment_type', 'settled_at']);
        });
    }
};
