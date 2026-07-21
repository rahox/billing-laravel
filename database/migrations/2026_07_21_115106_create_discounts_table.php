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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['flat', 'percentage']);
            $table->enum('mode', ['manual', 'prorate_activation'])
                ->comment('manual: dipilih bebas saat transaksi. prorate_activation: dihitung otomatis dari sisa hari periode saat aktivasi awal');
            $table->decimal('value', 15, 2)
                ->comment('Nominal rupiah jika type=flat, angka persen jika type=percentage');
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
        Schema::dropIfExists('discounts');
    }
};
