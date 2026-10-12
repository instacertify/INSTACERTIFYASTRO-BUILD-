<?php

namespace Tests\Feature;

use App\Support\Legacy\MediaImporter;
use Tests\TestCase;

class LegacyMediaImporterTest extends TestCase
{
    public function test_image_sources_resolve_to_one_absolute_url(): void
    {
        $media = new MediaImporter('https://instacertify.com', 'https://api.instacertify.com', enabled: false);

        $this->assertSame('https://instacertify.com/wp-content/uploads/a.jpg', $media->absolute('/wp-content/uploads/a.jpg'));
        $this->assertSame('https://api.instacertify.com/storage/b.png', $media->absolute('/storage/b.png'));
        $this->assertSame('https://cdn.example.com/c.png', $media->absolute('//cdn.example.com/c.png'));
        $this->assertSame(
            'https://instacertify.com/wp-content/uploads/2026/02/toys.jpg',
            $media->absolute('https://instacertify.comhttps://instacertify.com/wp-content/uploads/2026/02/toys.jpg'),
        );
    }
}
