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
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('product_movement_id')->nullable()->after('product_id')->constrained('product_movements')->onDelete('set null');
            $table->decimal('financial_value', 10, 2)->default(0)->after('adjustment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['product_movement_id']);
            $table->dropColumn(['product_movement_id', 'financial_value']);
        });
    }
};
