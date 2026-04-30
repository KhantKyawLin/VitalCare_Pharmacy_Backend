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
        Schema::table('orders', function (Blueprint $table) {
            // Make user_id nullable for walk-in customers
            $table->foreignId('user_id')->nullable()->change();
            
            // Add POS fields
            $table->string('order_type')->default('online'); // 'online' or 'walk-in'
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('received_amount', 10, 2)->nullable();
            $table->decimal('change_return', 10, 2)->nullable();
            $table->string('receipt_number')->nullable()->unique();
            $table->unsignedBigInteger('cashier_id')->nullable(); // The admin who processed the sale
            
            // Adding a foreign key for cashier_id if it references the users table
            $table->foreign('cashier_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->dropColumn([
                'order_type', 
                'discount_amount', 
                'tax_amount',
                'received_amount', 
                'change_return', 
                'receipt_number',
                'cashier_id'
            ]);
            
            // Revert user_id to non-nullable (might cause errors if walk-in data exists, so proceed with caution in real down migrations)
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
