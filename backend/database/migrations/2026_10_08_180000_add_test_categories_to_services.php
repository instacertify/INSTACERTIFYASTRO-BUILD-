<?php

use App\Models\Service;
use App\Support\TestCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('test_categories')->nullable()->after('group');
        });

        Service::query()->where('kind', 'testing')->each(
            fn (Service $service) => $service->forceFill(['test_categories' => TestCategories::guess($service)])->saveQuietly()
        );
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('test_categories');
        });
    }
};
