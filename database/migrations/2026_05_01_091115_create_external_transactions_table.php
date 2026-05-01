<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->string('category'); // e.g. Electricity, Rent, Salary, Transport
            $table->string('title');    // e.g. "April Meter Bill", "Monthly Rent"
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->string('reference_number')->nullable(); // receipt/invoice number
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_transactions');
    }
};
