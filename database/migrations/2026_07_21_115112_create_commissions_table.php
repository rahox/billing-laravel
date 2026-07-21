<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('base_amount', 15, 2)->comment('Nominal transaksi/pembayaran dasar perhitungan komisi');
            $table->enum('commission_type', ['flat', 'percentage']);
            $table->decimal('commission_value', 15, 2)->comment('Nilai persen atau flat pada saat komisi dihitung');
            $table->decimal('commission_amount', 15, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->date('earned_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
