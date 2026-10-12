<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_categories', function (Blueprint $table) {
            $table->string('group', 20)->default('mandatory')->after('slug');
        });

        Schema::table('standards', function (Blueprint $table) {
            $table->string('scheme', 10)->nullable()->after('path');
            $table->string('regulation_status', 20)->nullable()->index()->after('scheme');
            $table->date('mandatory_from')->nullable()->after('regulation_status');
            $table->string('mandatory_since', 60)->nullable()->after('mandatory_from');
            $table->string('sector', 80)->nullable()->after('mandatory_since');
            $table->string('is_keys')->nullable()->after('sector');
            $table->json('facts')->nullable()->after('products');
            $table->string('source', 20)->nullable()->after('legacy_id');
        });

        DB::table('standard_categories')->where('slug', 'iso-management-system')->update(['group' => 'management']);
        DB::table('standards')->whereIn('standard_category_id', DB::table('standard_categories')->where('slug', 'mandatory-crs-standards')->select('id'))->update(['scheme' => 'crs']);
        DB::table('standards')->whereIn('standard_category_id', DB::table('standard_categories')->where('slug', 'mandatory-bis-standard')->select('id'))->update(['scheme' => 'isi']);
        DB::table('standards')->whereIn('standard_category_id', DB::table('standard_categories')->where('slug', 'iso-management-system')->select('id'))->update(['scheme' => 'iso']);
    }

    public function down(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->dropColumn(['source', 'scheme', 'regulation_status', 'mandatory_from', 'mandatory_since', 'sector', 'is_keys', 'facts']);
        });
        Schema::table('standard_categories', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
