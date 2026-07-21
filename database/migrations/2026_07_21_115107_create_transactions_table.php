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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('discount_id')->nullable()->constrained('discounts')->nullOnDelete();
            $table->date('transaction_date');
            $table->date('period_start')->nullable()->comment('Awal periode layanan yang ditagih, untuk produk jasa recurring');
            $table->date('period_end')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->comment('(qty * unit_price) - discount_amount, sebelum pajak');
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('bhp_amount', 15, 2)->default(0);
            $table->decimal('uso_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->comment('subtotal + ppn_amount, belum dikurangi bhp/uso (bhp/uso beban internal bukan ditagih ke pelanggan)');
            $table->foreignId('invoice_id')->nullable();
            $table->enum('status', ['draft', 'invoiced', 'void'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
