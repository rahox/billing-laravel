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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('parent_reseller_id')->nullable()->after('phone')
                ->constrained('users')->nullOnDelete();
            $table->enum('commission_type', ['flat', 'percentage'])->nullable()->after('parent_reseller_id');
            $table->decimal('commission_value', 15, 2)->nullable()->after('commission_type');
            $table->boolean('is_active')->default(true)->after('commission_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_reseller_id');
            $table->dropColumn(['phone', 'commission_type', 'commission_value', 'is_active']);
        });
    }
};
