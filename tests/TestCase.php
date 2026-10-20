<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** Feature tests run signed in as an Owner unless a test class turns this off. */
    protected bool $signInAsOwner = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach the real internet (Apify, Claude, business websites).
        Http::preventStrayRequests();

        if ($this->signInAsOwner && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->actingAs(User::factory()->create(['role' => 'owner']));
        }
    }
}
