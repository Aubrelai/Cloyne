<?php

namespace Tests\Feature;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class V16Test extends TestCase
{
    use RefreshDatabase;

    private function failed(string $name, string $why): Business
    {
        return Business::create(['name' => $name, 'category' => 'Roofers', 'audit_status' => 'failed', 'audit_error' => $why]);
    }

    public function test_dashboard_says_why_audits_failed(): void
    {
        $this->failed('A Roofing', 'Claude rejected the API key. Check ANTHROPIC_API_KEY in your .env file.');
        $this->failed('B Roofing', 'Claude rejected the API key. Check ANTHROPIC_API_KEY in your .env file.');
        $this->failed('C Roofing', 'Claude took too long to answer. Try again in a minute.');

        $this->get('/')->assertOk()
            ->assertSee('3 audits failed')
            ->assertSeeInOrder(['2: Claude rejected the API key', '1: Claude took too long'])
            ->assertSee('Retry all');
    }

    public function test_retry_all_checks_the_key_first(): void
    {
        Queue::fake();
        $lead = $this->failed('A Roofing', 'Claude rejected the API key.');
        config(['services.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $this->post('/leads/audit-failed')->assertSessionHasErrors('audit');
        Queue::assertNothingPushed();

        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        \Illuminate\Support\Facades\Http::clearResolvedInstances();
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => Http::response(['data' => []])]);
        $response = $this->post('/leads/audit-failed');
        $response->assertSessionHasNoErrors()->assertSessionHas('status', 'Retrying 1 audit in the background.');
        Queue::assertPushed(AuditBusiness::class, 1);
        $this->assertSame('queued', $lead->fresh()->audit_status);
    }

    public function test_leads_filter_by_failed_and_by_crm(): void
    {
        $this->failed('Failed Roofing', 'x');
        Business::create(['name' => 'Crm Plumbing', 'category' => 'Plumbers', 'crm_id' => 'c1', 'crm_list' => 'Dallas']);
        Business::create(['name' => 'Finder Gym', 'category' => 'Gyms']);

        $this->get('/leads?audited=failed')->assertSee('Failed Roofing')->assertDontSee('Finder Gym');
        $this->get('/leads?from=crm')->assertSee('Crm Plumbing')->assertDontSee('Finder Gym');
        $this->get('/leads?from=finder')->assertSee('Finder Gym')->assertDontSee('Crm Plumbing');
    }

    public function test_export_downloads_the_filtered_leads_as_safe_csv(): void
    {
        $lead = Business::create(['name' => '=HYPERLINK("x")', 'category' => 'Roofers', 'phone' => '+12145550100', 'crm_id' => 'c1', 'crm_list' => 'Dallas']);
        $lead->recordAudit([['title' => 'No form', 'detail' => 'x', 'passed' => false, 'points' => 70]], 'Add booking.', 'ads_crm');
        Business::create(['name' => 'Other Gym', 'category' => 'Gyms']);

        $csv = $this->get('/leads/export?from=crm')->assertOk()->streamedContent();

        $this->assertStringContainsString('Business,Category,Phone', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('(214) 555-0100', $csv);
        $this->assertStringContainsString('Growth', $csv);
        $this->assertStringContainsString('Add booking.', $csv);
        $this->assertStringNotContainsString('Other Gym', $csv);
    }

    public function test_viewers_cannot_export_or_retry(): void
    {
        $this->actingAs(User::factory()->role('viewer')->create());
        $this->get('/leads/export')->assertForbidden();
        $this->post('/leads/audit-failed')->assertForbidden();
    }
}
