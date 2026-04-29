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
        Schema::table('promotions', function (Blueprint $table) {
            $table->enum('promotion_scope', ['item', 'order'])->default('item')->after('description');
            $table->integer('min_qty_requirement')->default(1)->after('discount_value');
            $table->decimal('min_order_value', 10, 2)->default(0)->after('min_qty_requirement');
            $table->integer('max_usage_per_bill')->nullable()->after('min_order_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'promotion_scope',
                'min_qty_requirement',
                'min_order_value',
                'max_usage_per_bill'
            ]);
        });
    }
};
