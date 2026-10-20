<?php

namespace Tests\Feature;

use App\Models\Business;
use Database\Seeders\LeadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_leads_page_lists_businesses_sorted_by_score(): void
    {
        $this->seed(LeadSeeder::class);

        $this->get('/leads')
            ->assertOk()
            ->assertSeeInOrder(['Sunshine Roofing Co.', 'Coastal Air Pros', 'Gulf Coast Pools']);
    }

    public function test_score_filter_only_shows_hot_leads(): void
    {
        $this->seed(LeadSeeder::class);

        $this->get('/leads?score=hot')
            ->assertOk()
            ->assertSee('Sunshine Roofing Co.')
            ->assertDontSee('Gulf Coast Pools');
    }

    public function test_package_filter_only_shows_matching_leads(): void
    {
        $this->seed(LeadSeeder::class);

        $this->get('/leads?package=website')
            ->assertOk()
            ->assertSee('Casa Verde Taqueria')
            ->assertDontSee('Sunshine Roofing Co.');
    }

    public function test_recording_an_audit_updates_the_business_score(): void
    {
        $business = Business::create(['name' => 'Test Roofing', 'category' => 'Roofing']);

        $business->recordAudit([
            ['title' => 'Active Meta ads', 'detail' => '1 ad', 'passed' => true, 'points' => 25],
            ['title' => 'Meta Pixel installed', 'detail' => 'Found', 'passed' => true, 'points' => 15],
        ], 'Running ads with no booking.', 'ads_crm');

        $this->assertSame(40, $business->fresh()->score);
        $this->assertSame('ads_crm', $business->fresh()->package);
        $this->assertSame('audited', $business->fresh()->stage);
    }

    public function test_empty_database_shows_a_helpful_message(): void
    {
        $this->get('/leads')->assertOk()->assertSee('No businesses yet');
    }
}
