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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('entry_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('invoice_date');
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date');
            $table->index('status');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->index('earned_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['entry_date']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['status']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropIndex(['earned_date']);
            $table->dropIndex(['status']);
        });
    }
};
