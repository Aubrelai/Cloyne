<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Search;
use App\Services\LeadImporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Things Cloyne needs when it runs online (DreamHost, MySQL, cron). */
class DeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_runs_the_worker_and_checks_searches(): void
    {
        $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

        $this->assertStringContainsString('queue:work --stop-when-empty', $commands);
        $this->assertStringContainsString('cloyne:sync-searches', $commands);
    }

    public function test_sync_command_imports_finished_searches(): void
    {
        config(['services.apify.token' => 'test-token', 'services.anthropic.auto_audit' => false]);
        Search::create(['category' => 'Gyms', 'area' => 'Miami, FL', 'max_results' => 50,
            'apify_run_id' => 'run1', 'apify_dataset_id' => 'ds1']);
        Http::fake([
            'api.apify.com/v2/actor-runs/run1' => Http::response(['data' => ['status' => 'SUCCEEDED']]),
            'api.apify.com/v2/datasets/ds1/items*' => Http::response([['title' => 'Iron Gym', 'city' => 'Miami', 'placeId' => 'g1']]),
        ]);

        $this->artisan('cloyne:sync-searches')->assertSuccessful();

        $this->assertSame('done', Search::sole()->status);
        $this->assertSame(['Iron Gym'], Business::pluck('name')->all());
    }

    public function test_sync_command_does_nothing_without_apify(): void
    {
        Http::fake();
        $this->artisan('cloyne:sync-searches')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_long_values_fit_mysql_columns(): void
    {
        $search = Search::create(['category' => 'Gyms', 'area' => 'Miami, FL', 'max_results' => 50]);

        app(LeadImporter::class)->import($search, [[
            'title' => 'Long Gym', 'city' => 'Miami', 'placeId' => 'long1',
            'website' => 'https://longgym.example/landing?'.implode('&', array_map(fn ($i) => "p{$i}=value{$i}", range(1, 40))),
            'street' => str_repeat('1 Very Long Street ', 30),
        ]]);

        $gym = Business::sole();
        $this->assertSame('https://longgym.example/landing', $gym->website);
        $this->assertLessThanOrEqual(250, strlen($gym->street));
    }
}
