<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_map_lists_leads_anywhere_and_loads_the_map_script(): void
    {
        Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'city' => 'Miami', 'latitude' => 25.76, 'longitude' => -80.19, 'score' => 72]);
        Business::create(['name' => 'London Gym', 'category' => 'Gyms', 'city' => 'London', 'latitude' => 51.5, 'longitude' => -0.12]);
        Business::create(['name' => 'No Location Roofing', 'category' => 'Roofers']);
        $hidden = Business::create(['name' => 'Excluded Spa', 'category' => 'Spas', 'latitude' => 26.1, 'longitude' => -80.1]);
        $hidden->exclude('Not a fit');

        $response = $this->get('/')->assertOk();
        $points = json_decode(html_entity_decode($this->attribute($response->getContent(), 'data-lead-map-points')), true);

        $names = array_column($points, 'name');
        sort($names);
        $this->assertSame(['Bright Smile Dental', 'London Gym'], $names);
        $miami = collect($points)->firstWhere('name', 'Bright Smile Dental');
        $this->assertTrue($miami['hot']);
        $this->assertSame('Dentists · Miami', $miami['about']);
        $response->assertSee('js/lead-map.js', false)->assertSee('leaflet', false);
    }

    public function test_empty_dashboard_map_skips_the_map_script(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Leads appear here after your first search.')
            ->assertDontSee('js/lead-map.js', false);
    }

    private function attribute(string $html, string $name): string
    {
        preg_match('/'.preg_quote($name, '/')."='([^']*)'/", $html, $m);

        return $m[1] ?? '[]';
    }
}
