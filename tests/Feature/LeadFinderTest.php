<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.apify.token' => 'test-token', 'services.apify.maps_actor' => 'compass/crawler-google-places']);
    }

    public function test_starting_a_search_starts_an_apify_run(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/compass~crawler-google-places/runs*' => Http::response([
                'data' => ['id' => 'run123', 'defaultDatasetId' => 'ds123', 'status' => 'READY'],
            ], 201),
        ]);

        $this->post('/lead-finder/searches', ['category' => 'Dentists', 'area' => 'Dallas, TX', 'max_results' => 100])
            ->assertRedirect('/lead-finder');

        $search = Search::sole();
        $this->assertSame('running', $search->status);
        $this->assertSame('run123', $search->apify_run_id);

        Http::assertSent(fn ($request) => $request['searchStringsArray'] === ['Dentists']
            && $request['locationQuery'] === 'Dallas, TX'
            && $request['maxCrawledPlacesPerSearch'] === 100
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_a_finished_search_imports_businesses(): void
    {
        $search = Search::create(['category' => 'dentists', 'area' => 'Dallas, TX', 'max_results' => 100,
            'apify_run_id' => 'run123', 'apify_dataset_id' => 'ds123']);
        Business::create(['name' => 'Already Here Dental', 'category' => 'Dentists', 'city' => 'Dallas', 'place_id' => 'p2']);

        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response(['data' => ['status' => 'SUCCEEDED']]),
            'api.apify.com/v2/datasets/ds123/items*' => Http::response([
                ['title' => 'Bright Smile Dental', 'categoryName' => 'Dentist', 'city' => 'Dallas', 'state' => 'Texas',
                    'phone' => '(214) 555-0100', 'website' => 'brightsmile.example', 'totalScore' => 4.83, 'reviewsCount' => 120,
                    'location' => ['lat' => 32.78, 'lng' => -96.8], 'placeId' => 'p1'],
                ['title' => 'Already Here Dental', 'city' => 'Dallas', 'state' => 'Texas', 'placeId' => 'p2', 'reviewsCount' => 9],
                ['title' => 'Closed Dental', 'city' => 'Dallas', 'state' => 'Texas', 'placeId' => 'p3', 'permanentlyClosed' => true],
            ]),
        ]);

        $this->get('/lead-finder')->assertOk()->assertSee('Done');

        $search->refresh();
        $this->assertSame('done', $search->status);
        $this->assertSame(3, $search->found);
        $this->assertSame(1, $search->added);
        $this->assertSame(1, $search->skipped_existing);
        $this->assertSame(1, $search->skipped_closed);

        $new = Business::where('place_id', 'p1')->sole();
        $this->assertSame('TX', $new->state);
        $this->assertSame('Dentists', $new->category);
        $this->assertSame('Dentist', $new->google_category);
        $this->assertSame('https://brightsmile.example', $new->website);
        $this->assertSame('new', $new->stage);
        $this->assertSame($search->id, $new->search_id);

        $this->assertSame(9, Business::where('place_id', 'p2')->sole()->review_count);
        $this->assertDatabaseMissing('businesses', ['place_id' => 'p3']);
    }

    public function test_a_running_search_stays_running(): void
    {
        Search::create(['category' => 'Gyms', 'area' => 'Denver, CO', 'max_results' => 50, 'apify_run_id' => 'run9', 'apify_dataset_id' => 'ds9']);
        Http::fake(['api.apify.com/v2/actor-runs/run9' => Http::response(['data' => ['status' => 'RUNNING']])]);

        $this->get('/lead-finder')->assertOk()->assertSee('Searching');
        $this->assertSame('running', Search::sole()->status);
    }

    public function test_a_failed_run_is_marked_failed(): void
    {
        Search::create(['category' => 'Gyms', 'area' => 'Denver, CO', 'max_results' => 50, 'apify_run_id' => 'run9', 'apify_dataset_id' => 'ds9']);
        Http::fake(['api.apify.com/v2/actor-runs/run9' => Http::response(['data' => ['status' => 'FAILED']])]);

        $this->get('/lead-finder')->assertOk()->assertSee('Failed');
        $this->assertSame('failed', Search::sole()->status);
    }

    public function test_without_a_key_it_asks_you_to_connect_apify(): void
    {
        config(['services.apify.token' => null]);
        Http::fake();

        $this->get('/lead-finder')->assertOk()->assertSee('Connect Apify');
        $this->post('/lead-finder/searches', ['category' => 'Dentists', 'area' => 'Dallas, TX', 'max_results' => 100])
            ->assertSessionHasErrors('apify');

        Http::assertNothingSent();
        $this->assertSame(0, Search::count());
    }

    public function test_us_state_names_are_not_read_as_countries(): void
    {
        $this->assertSame('georgia, USA', \App\Services\Apify::location(' georgia'));
        $this->assertSame('Atlanta, Georgia, USA', \App\Services\Apify::location('Atlanta, Georgia'));
        $this->assertSame('Dallas, TX', \App\Services\Apify::location('Dallas, TX'));
        $this->assertSame('Tbilisi, Georgia, Europe', \App\Services\Apify::location('Tbilisi, Georgia, Europe'));
    }

    public function test_apify_spending_limit_gets_a_plain_message(): void
    {
        config(['services.apify.token' => 'test-token']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['error' => ['type' => 'platform-feature-disabled', 'message' => 'Monthly usage hard limit exceeded']], 403)]);

        try {
            app(\App\Services\Apify::class)->startMapsSearch('Roofers', 'Dallas, TX', 10);
            $this->fail('Expected an error.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('monthly spending limit is used up', $e->getMessage());
        }
    }
}
