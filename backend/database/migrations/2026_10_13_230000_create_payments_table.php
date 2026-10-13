<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->string('gateway', 20);
            $table->string('status', 20)->default('created')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('company')->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
