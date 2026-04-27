<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('supply_product_id')->nullable()->constrained('supply_products')->onDelete('set null');
            $table->bigInteger('instock_quantity');
            $table->date('manufactured_date')->nullable();
            $table->date('expired_date');
            $table->enum('movement_type', ['current', 'stored', 'sold-out']);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->timestamp('movement_date')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_movements');
    }
};
