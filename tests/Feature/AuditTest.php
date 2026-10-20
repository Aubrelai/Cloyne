<?php

namespace Tests\Feature;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Services\Auditor;
use App\Services\WebsiteScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = <<<'HTML'
        <html><head><title>Bright Smile Dental | Dallas Dentist</title>
        <script src="https://connect.facebook.net/en_US/fbevents.js"></script><script>fbq('init', '123');</script>
        </head><body>
        <a href="/contact-us">Contact us</a> <a href="tel:2145550100">Call</a>
        <p>Family dentistry in Dallas since 2009.</p>
        <footer>© 2019 Bright Smile Dental · <a href="https://www.facebook.com/brightsmiledallas">Facebook</a></footer>
        </body></html>
        HTML;

    private const CONTACT = <<<'HTML'
        <html><body><p>Email us: <a href="mailto:hello@brightsmile.example">hello@brightsmile.example</a></p>
        <img src="logo@2x.png"></body></html>
        HTML;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key', 'services.anthropic.audit_model' => 'claude-haiku-5-5', 'cloyne.selling' => ['website', 'ads_crm', 'scale']]);
    }

    private function fakeClaude(array $input): void
    {
        Http::fake([
            'brightsmile.example/contact-us' => Http::response(self::CONTACT),
            'brightsmile.example*' => Http::response(self::HOME),
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'tool_use', 'name' => 'record_audit', 'input' => $input]],
            ]),
        ]);
    }

    public function test_scanner_reads_the_website_and_contact_page(): void
    {
        Http::fake([
            'brightsmile.example/contact-us' => Http::response(self::CONTACT),
            'brightsmile.example*' => Http::response(self::HOME),
        ]);

        $signals = app(WebsiteScanner::class)->scan('https://brightsmile.example');

        $this->assertTrue($signals['reachable']);
        $this->assertTrue($signals['meta_pixel']);
        $this->assertFalse($signals['mobile_viewport_tag']);
        $this->assertFalse($signals['has_form']);
        $this->assertTrue($signals['phone_link']);
        $this->assertSame(2019, $signals['copyright_year']);
        $this->assertSame(['hello@brightsmile.example'], $signals['emails']);
        $this->assertSame('https://www.facebook.com/brightsmiledallas', $signals['facebook_page']);
    }

    public function test_scanner_treats_a_facebook_link_as_no_website(): void
    {
        Http::fake();

        $signals = app(WebsiteScanner::class)->scan('https://www.facebook.com/somebusiness');

        $this->assertFalse($signals['has_website']);
        Http::assertNothingSent();
    }

    public function test_audit_saves_score_packages_pitch_and_email(): void
    {
        $this->fakeClaude([
            'checks' => [
                ['title' => 'Mobile-friendly', 'detail' => 'No viewport tag', 'found' => false, 'points' => 20],
                ['title' => 'Lead or booking form', 'detail' => 'None found', 'found' => false, 'points' => 20],
                ['title' => 'Meta Pixel', 'detail' => 'Installed, likely ran ads', 'found' => true, 'points' => 15],
                ['title' => 'Google reviews', 'detail' => '4.8 from 120', 'found' => true, 'points' => 10],
            ],
            'package' => 'ads_crm',
            'pitch_package' => 'website',
            'pitch' => 'Your site has Meta tracking but no way to book from a phone.',
            'email' => 'hello@brightsmile.example',
            'contact_name' => null,
            'disqualified' => false,
        ]);
        $business = Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'website' => 'https://brightsmile.example', 'audit_status' => 'queued']);

        $audit = app(Auditor::class)->audit($business);

        $business->refresh();
        $this->assertSame(65, $business->score);
        $this->assertSame('ads_crm', $business->package);
        $this->assertSame('website', $business->pitch_package);
        $this->assertSame('audited', $business->stage);
        $this->assertNull($business->audit_status);
        $this->assertSame('hello@brightsmile.example', $business->primaryContact->email);
        $this->assertTrue($audit->signals['meta_pixel']);
        $this->assertCount(4, $audit->checks);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->hasHeader('x-api-key', 'test-key')
            && $r['tool_choice']['name'] === 'record_audit'
            && str_contains($r['system'][0]['text'], 'Growth — Ads + CRM'));
    }

    public function test_audit_ignores_an_email_the_scan_did_not_find(): void
    {
        $this->fakeClaude([
            'checks' => [['title' => 'Website', 'detail' => 'Loads', 'found' => true, 'points' => 0]],
            'package' => 'website', 'pitch_package' => null, 'pitch' => 'x',
            'email' => 'made.up@nowhere.example', 'disqualified' => false,
        ]);
        $business = Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'website' => 'https://brightsmile.example']);

        app(Auditor::class)->audit($business);

        $this->assertSame(0, $business->contacts()->count());
    }

    public function test_disqualified_business_is_excluded(): void
    {
        $this->fakeClaude([
            'checks' => [['title' => 'Business type', 'detail' => 'National chain', 'found' => true, 'points' => 0]],
            'package' => 'scale', 'pitch_package' => null, 'pitch' => 'n/a', 'email' => null,
            'disqualified' => true, 'disqualify_reason' => 'National chain, no local owner to sell to',
        ]);
        $business = Business::create(['name' => 'Big Chain Dental', 'category' => 'Dentists', 'website' => 'https://brightsmile.example']);

        app(Auditor::class)->audit($business);

        $business->refresh();
        $this->assertSame('excluded', $business->stage);
        $this->assertNull($business->package);
    }

    public function test_audit_button_queues_unaudited_leads(): void
    {
        Queue::fake();
        Business::create(['name' => 'A', 'category' => 'Gyms']);
        Business::create(['name' => 'B', 'category' => 'Gyms']);
        Business::create(['name' => 'C', 'category' => 'Gyms', 'score' => 70]);

        $this->post('/leads/audit')->assertRedirect();

        Queue::assertPushed(AuditBusiness::class, 2);
        $this->assertSame(2, Business::where('audit_status', 'queued')->count());
    }

    public function test_failed_audit_is_marked_on_the_lead(): void
    {
        Http::fake([
            'brightsmile.example*' => Http::response(self::HOME),
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401),
        ]);
        $business = Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'website' => 'https://brightsmile.example']);

        $this->post("/leads/{$business->id}/audit")->assertSessionHasErrors('audit');

        $this->assertSame('failed', $business->fresh()->audit_status);
        $this->assertStringContainsString('API key', $business->fresh()->audit_error);
    }
}
