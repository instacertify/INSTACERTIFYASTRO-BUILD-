<?php

use App\Models\Industry;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/** URLs listed in the live sitemap must keep answering at the same path instead of redirecting elsewhere. */
return new class extends Migration
{
    public function up(): void
    {
        $served = Industry::pluck('slug')->map(fn ($slug) => "/{$slug}")->push('/sitemap', '/en71-toy-testing-india');

        Redirect::whereIn('from_path', $served)->delete();
        Redirect::updateOrCreate(['from_path' => '/en71'], ['to_path' => '/en71-toy-testing-india', 'status_code' => 301]);
    }

    public function down(): void
    {
        Redirect::where('from_path', '/en71')->delete();
    }
};
