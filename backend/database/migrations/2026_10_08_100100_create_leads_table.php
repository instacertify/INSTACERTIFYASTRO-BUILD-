<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->default('quote')->index();
            $table->string('status', 32)->default('new')->index();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('company')->nullable();
            $table->string('country', 64)->nullable();
            $table->string('manufacturer_type', 32)->nullable();
            $table->string('service_path')->nullable()->index();
            $table->string('product')->nullable();
            $table->string('timeline', 32)->nullable();
            $table->text('message')->nullable();
            $table->string('source_page')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
