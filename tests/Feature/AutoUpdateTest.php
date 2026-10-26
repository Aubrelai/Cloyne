<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AutoUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->useStoragePath(sys_get_temp_dir().'/cloyne-autoupdate-'.uniqid());
        @mkdir(storage_path('app'), 0777, true);
    }

    public function test_settings_shows_the_servers_last_check(): void
    {
        $this->get('/settings')->assertSee('Install new versions automatically')->assertSee('Starts after the next update by hand');

        file_put_contents(AutoUpdate::statusFile(), json_encode(['state' => 'updated', 'message' => 'Installed the newest version.',
            'checked_at' => now()->subMinutes(3)->toIso8601String(), 'updated_at' => now()->subMinutes(3)->toIso8601String(), 'commit' => 'abc1234']));
        $this->get('/settings')->assertSee('Installed the newest version.')->assertSee('last installed');

        file_put_contents(AutoUpdate::statusFile(), json_encode(['state' => 'failed', 'message' => 'The last update stopped part way.', 'checked_at' => now()->toIso8601String()]));
        $this->get('/settings')->assertSee('Stopped');

        file_put_contents(AutoUpdate::statusFile(), 'not json');
        $this->get('/settings')->assertOk();
    }

    public function test_owner_turns_automatic_updates_off_and_on(): void
    {
        $this->put('/settings/updates', ['on' => 0])->assertRedirect();
        $this->assertFileExists(AutoUpdate::offFile());
        $this->get('/settings')->assertSee('Turn on');

        $this->put('/settings/updates', ['on' => 1]);
        $this->assertFileDoesNotExist(AutoUpdate::offFile());

        $this->actingAs(User::factory()->role('reviewer')->create());
        $this->put('/settings/updates', ['on' => 0])->assertForbidden();
    }

    public function test_server_scripts_are_valid_bash(): void
    {
        foreach (['deploy/auto-update.sh', 'deploy/dreamhost.sh'] as $script) {
            exec('bash -n '.escapeshellarg(base_path($script)).' 2>&1', $out, $code);
            $this->assertSame(0, $code, $script.': '.implode("\n", $out));
        }
        $this->assertStringContainsString('auto-update.sh', file_get_contents(base_path('deploy/dreamhost.sh')));
    }
}
