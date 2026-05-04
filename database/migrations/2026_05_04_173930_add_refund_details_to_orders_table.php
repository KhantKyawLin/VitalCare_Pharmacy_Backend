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
            $table->text('refund_reason')->nullable()->after('payment_status');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
            $table->unsignedBigInteger('refunded_by')->nullable()->after('refunded_at');
            $table->foreign('refunded_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
