<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Products table indexes
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('unit_id');
            $table->index('is_published');
            $table->index('name'); // For name searches
        });

        // 2. Product Movements table indexes
        Schema::table('product_movements', function (Blueprint $table) {
            $table->index('expired_date');
            $table->index('movement_type');
            $table->index('movement_date');
        });

        // 3. Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });

        // 4. Health Tips table indexes
        Schema::table('health_tips', function (Blueprint $table) {
            $table->index('is_published');
            $table->index('created_at');
        });

        // 5. Activity Logs table indexes
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['is_published']);
            $table->dropIndex(['name']);
        });

        Schema::table('product_movements', function (Blueprint $table) {
            $table->dropIndex(['expired_date']);
            $table->dropIndex(['movement_type']);
            $table->dropIndex(['movement_date']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('health_tips', function (Blueprint $table) {
            $table->dropIndex(['is_published']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
        });
    }
};
