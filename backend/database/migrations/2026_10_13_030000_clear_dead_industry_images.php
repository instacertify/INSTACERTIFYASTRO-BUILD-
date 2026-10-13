<?php

use App\Models\Industry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** These old-site images no longer exist (404); without one the website shows its own industry photo. */
    public function up(): void
    {
        Industry::where('image', 'like', 'https://instacertify.com/images/industries/%')
            ->get()
            ->each(fn (Industry $industry) => $industry->update(['image' => null]));
    }

    public function down(): void
    {
        //
    }
};
