<?php

namespace Tests\Feature;

use App\Jobs\WriteCallScript;
use App\Models\Business;
use App\Services\CallScripter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallScriptTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $extra = []): Business
    {
        $b = Business::create($extra + ['name' => 'Sunshine Roofing', 'category' => 'Roofers', 'city' => 'Wellington', 'state' => 'FL', 'phone' => '+15615550100', 'rating' => 4.8, 'review_count' => 120]);
        $b->recordAudit([['title' => 'Mobile-friendly', 'detail' => 'Doesn\'t resize on phones', 'passed' => false, 'points' => 78]], 'Loved on Google, hard to use on phones.', 'website');

        return $b->fresh();
    }

    private function fakeClaude(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'record_call_script', 'input' => [
            'opener' => 'I was looking at Sunshine Roofing on Google: 4.8 stars from 120 reviews. Your site doesn\'t fit a phone screen. Want more quote requests from it?',
            'objections' => [
                ['they_say' => 'Just send me an email.', 'you_say' => 'Happy to. What\'s the best address?'],
                ['they_say' => 'How much is it?', 'you_say' => 'It depends on what you need; that\'s what the 15-minute call is for.'],
                ['they_say' => '', 'you_say' => 'dropped'],
            ],
        ]]], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 300]])]);
    }

    public function test_script_is_written_from_the_audit_without_prices(): void
    {
        $this->fakeClaude();
        $lead = $this->lead();

        $script = app(CallScripter::class)->write($lead);

        $this->assertStringContainsString('4.8 stars', $script['opener']);
        $this->assertCount(2, $script['objections']);
        $this->assertSame('https://admin.laiqdigital.com/booking/laiq-digital/strategy-call', $script['booking_url']);
        $this->assertNotNull($lead->fresh()->call_script_at);
        Http::assertSent(fn ($r) => $r['model'] === 'claude-haiku-5-5' && $r['tool_choice']['name'] === 'record_call_script'
            && str_contains($r['system'][0]['text'], 'without any number') && str_contains($r['messages'][0]['content'], '4.8 stars from 120 reviews'));
        $this->assertDatabaseHas('api_costs', ['purpose' => 'script']);
    }

    public function test_crm_receives_the_script_and_lead_profile_shows_it(): void
    {
        $this->fakeClaude();
        $lead = $this->lead(['crm_id' => 'c7']);
        app(CallScripter::class)->write($lead);
        $key = $this->post('/settings/costs-key')->getSession()->get('costs_key');

        $this->withToken($key)->getJson('/api/crm/leads?crm_ids=c7')
            ->assertJsonPath('leads.0.call_script.objections.1.they_say', 'How much is it?')
            ->assertJsonPath('leads.0.call_script.goal', 'Book a 15-minute strategy call');

        $this->get('/leads?lead='.$lead->id)->assertSee('Call script')->assertSee('"Just send me an email."', false)->assertSee('Rewrite script');
    }

    public function test_scripts_are_queued_for_call_list_leads_only(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key', 'services.anthropic.auto_script' => true]);
        $crm = $this->lead(['name' => 'Crm Roofing', 'crm_id' => 'c1']);
        $this->lead(['name' => 'No Phone Spa', 'phone' => null]);
        $cold = Business::create(['name' => 'Cold Gym', 'category' => 'Gyms', 'phone' => '+15615550199']);
        $cold->recordAudit([['title' => 'x', 'detail' => 'x', 'passed' => true, 'points' => 10]], 'p', 'website');

        $this->artisan('cloyne:write-scripts')->expectsOutput('Queued 1 call script.');
        Queue::assertPushed(WriteCallScript::class, fn ($job) => $job->business->is($crm));

        $this->assertTrue(WriteCallScript::wanted($crm));
        $this->assertFalse(WriteCallScript::wanted($cold->fresh()));
    }

    public function test_write_now_button_needs_an_audit(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key']);
        $fresh = Business::create(['name' => 'New Roofing', 'category' => 'Roofers', 'phone' => '+15615550111']);
        $this->post('/leads/'.$fresh->id.'/script')->assertSessionHasErrors('audit');

        $this->post('/leads/'.$this->lead()->id.'/script')->assertSessionHas('status');
        Queue::assertPushed(WriteCallScript::class);
    }
}
