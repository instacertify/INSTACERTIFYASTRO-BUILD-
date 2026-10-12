<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('department', 80)->nullable()->index();
            $table->string('employment_type', 20)->default('FULL_TIME');
            $table->string('workplace', 10)->default('onsite');
            $table->string('city', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('country', 2)->default('IN');
            $table->unsignedTinyInteger('experience_min')->nullable();
            $table->unsignedTinyInteger('experience_max')->nullable();
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->boolean('salary_visible')->default(false);
            $table->unsignedSmallInteger('openings')->default(1);
            $table->string('summary', 300)->nullable();
            $table->longText('description')->nullable();
            $table->json('skills')->nullable();
            $table->string('status', 10)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('vacancy_user', function (Blueprint $table) {
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['vacancy_id', 'user_id']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vacancy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->string('name', 120);
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('current_ctc', 40)->nullable();
            $table->string('expected_ctc', 40)->nullable();
            $table->string('notice_period', 20)->nullable();
            $table->text('cover_letter')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_name')->nullable();
            $table->unsignedInteger('cv_size')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_page')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->boolean('consent')->default(false);
            $table->string('consent_version', 20)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('vacancy_user');
        Schema::dropIfExists('vacancies');
    }
};
