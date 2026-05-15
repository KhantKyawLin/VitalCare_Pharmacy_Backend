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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_prescription')->default(false)->after('price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('prescription_image')->nullable()->after('slip_image');
            $table->enum('prescription_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('prescription_image');
        });

        Schema::table('product_movements', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('movement_type');
        });

        Schema::create('order_product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_movement_id')->constrained('product_movements')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product_batches');
        
        Schema::table('product_movements', function (Blueprint $table) {
            $table->dropColumn('batch_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['prescription_image', 'prescription_status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('requires_prescription');
        });
    }
};
