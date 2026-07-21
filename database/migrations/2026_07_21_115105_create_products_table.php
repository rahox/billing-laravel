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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->enum('type', ['jasa', 'barang']);
            $table->decimal('price', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0)
                ->comment('Harga pokok/beban pokok per unit, dasar jurnal HPP');
            $table->boolean('is_recurring')->default(false)
                ->comment('Hanya relevan untuk type=jasa');
            $table->enum('recurring_period', ['monthly', 'quarterly', 'yearly'])->nullable();
            $table->boolean('is_ppn_applicable')->default(true);
            $table->boolean('is_telco_levy_applicable')->default(false)
                ->comment('Kena BHP 0.25% & USO 1.25%, khusus jasa internet');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
