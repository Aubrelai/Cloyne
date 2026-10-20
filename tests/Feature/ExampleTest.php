<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every screen loads without errors on an empty database.
     */
    public function test_every_screen_loads(): void
    {
        foreach (['/', '/lead-finder', '/leads', '/review', '/campaigns', '/inbox', '/booked', '/settings', '/account'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
