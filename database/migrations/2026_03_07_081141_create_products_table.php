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
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->text('description')->nullable();
            $table->text('usage')->nullable();
            $table->text('side_effects')->nullable();
            $table->string('dosage')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->integer('minimum_quantity')->default(1);
            $table->boolean('reorder_status')->default(false);
            $table->boolean('is_expired')->default(false);
            $table->decimal('price', 10, 2)->default(0);
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
