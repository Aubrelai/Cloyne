<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\Drafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V18Test extends TestCase
{
    use RefreshDatabase;

    private function hotLead(string $email = 'dana@brightsmile.example'): Business
    {
        config(['services.anthropic.key' => 'test-key']);
        $lead = Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'website' => 'https://brightsmile.example']);
        $lead->contacts()->create(['email' => $email, 'name' => 'Dana']);
        $lead->recordAudit([['title' => 'Mobile-friendly', 'detail' => 'No viewport', 'passed' => false, 'points' => 70]], 'Fix phones.', 'website');

        return $lead->fresh();
    }

    private function fake(string $result): void
    {
        Http::fake([
            'api.millionverifier.com/api/v3/credits*' => Http::response(['credits' => 4210, 'error' => '']),
            'api.millionverifier.com/*' => Http::response(['email' => 'x', 'result' => $result, 'error' => '', 'credits' => 4209]),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'record_email', 'input' => [
                'angle' => 'Phones', 'subject' => 'your site on phones', 'body' => "Hi Dana,\n\nYour site doesn't resize on phones.\n\nWorth a call?",
            ]]]]),
        ]);
    }

    public function test_invalid_email_is_never_written_to(): void
    {
        config(['services.millionverifier.key' => 'mv-key']);
        $this->fake('invalid');
        $lead = $this->hotLead();

        $draft = app(Drafter::class)->write($lead);

        $this->assertSame('skipped', $draft->status);
        $this->assertStringContainsString('failed the email check', $draft->error);
        $this->assertSame(['invalid', 'invalid'], [$lead->contacts()->value('email_status'), $lead->contacts()->value('email_check_result')]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'anthropic'));
        $this->assertFalse(Business::draftable()->whereKey($lead->id)->exists());
    }

    public function test_good_email_is_verified_then_drafted_and_checked_only_once(): void
    {
        config(['services.millionverifier.key' => 'mv-key']);
        $this->fake('ok');
        $lead = $this->hotLead();

        $this->assertSame('pending', app(Drafter::class)->write($lead)->status);
        $this->assertSame('verified', $lead->contacts()->value('email_status'));

        app(Drafter::class)->write($lead->fresh(), null, null);
        Http::assertSentCount(3); // 1 check + 2 drafts
    }

    public function test_catch_all_counts_as_risky_but_still_drafted(): void
    {
        config(['services.millionverifier.key' => 'mv-key']);
        $this->fake('catch_all');
        $lead = $this->hotLead();

        $this->assertSame('pending', app(Drafter::class)->write($lead)->status);
        $this->assertSame('risky', $lead->contacts()->value('email_status'));
    }

    public function test_without_a_key_nothing_is_checked(): void
    {
        $this->fake('invalid');
        $lead = $this->hotLead();

        $this->assertSame('pending', app(Drafter::class)->write($lead)->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'millionverifier'));
    }

    public function test_settings_shows_and_tests_the_connection(): void
    {
        $this->get('/settings')->assertSee('MillionVerifier')->assertSee('Not connected');
        config(['services.millionverifier.key' => 'mv-key']);
        $this->fake('ok');
        $this->post('/settings/test/millionverifier')->assertSessionHas('status', 'MillionVerifier works. 4,210 credits left.');
    }
}
