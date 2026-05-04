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
            $table->foreignId('gift_product_id')->nullable()->constrained('products')->after('max_usage_per_bill');
            $table->integer('gift_qty')->default(0)->after('gift_product_id');
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('gift_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_product_id');
            $table->dropColumn(['gift_qty', 'max_discount_amount']);
        });
    }
};
