<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('market', 60);
            $table->string('country', 2);
            $table->string('registration_label', 20)->nullable();
            $table->string('registration_number', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('show_on_website')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        DB::table('legal_entities')->insert([
            'name' => 'Instacertify Labs Private Limited',
            'market' => 'India',
            'country' => 'IN',
            'registration_label' => 'CIN',
            'registration_number' => 'U74999UP2022PTC170291',
            'address' => 'A-34, Sector 63A, Noida, Uttar Pradesh 201301, India',
            'is_primary' => true,
            'show_on_website' => false,
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entities');
    }
};
