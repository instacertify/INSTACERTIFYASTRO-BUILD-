<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_api_home_sends_visitors_to_the_website(): void
    {
        config(['instacertify.frontend_url' => 'https://instacertify.com']);

        $this->get('/')->assertRedirect('https://instacertify.com/');
    }
}
