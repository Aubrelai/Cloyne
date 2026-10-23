<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_the_newest_version_and_whats_new_lists_them_newest_first(): void
    {
        config(['versions' => [
            ['version' => '1.2', 'date' => '2026-10-22', 'title' => 'Map', 'notes' => ['Real map.']],
            ['version' => '1.10', 'date' => '2026-11-02', 'title' => 'Later', 'notes' => ['Newer.']],
            ['version' => '0.9', 'date' => '2026-10-20', 'title' => 'Early', 'notes' => ['Old.']],
        ]]);

        $this->get('/')->assertOk()->assertSee('v1.10 · Nov 2');
        $this->get('/versions')->assertOk()->assertSeeInOrder(['v1.10', 'Later', 'v1.2', 'Map', 'v0.9', 'Early']);
    }

    public function test_real_version_list_is_valid(): void
    {
        foreach (config('versions') as $v) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $v['version']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $v['date']);
            $this->assertNotEmpty($v['notes']);
        }
    }
}
