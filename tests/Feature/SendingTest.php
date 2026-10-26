<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Draft;
use App\Models\Reply;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'api.instantly.ai/api/v2';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.instantly.key' => 'inst-key', 'cloyne.outreach.mailing_address' => 'LaiQ Digital, 1 Main St Ste 5, Miami, FL 33101', 'services.instantly.per_inbox_daily' => 2]);
    }

    private function fakeInstantly(array $accounts = null, array $leads = [], array $emails = []): void
    {
        $accounts ??= [['email' => 'aubre@getlaiqdigital.com', 'status' => 1, 'warmup_status' => 1, 'stat_warmup_score' => 92]];
        Http::fake([
            self::API.'/accounts*' => Http::response(['items' => $accounts]),
            self::API.'/campaigns/*/activate' => Http::response(['id' => 'camp-1', 'status' => 1]),
            self::API.'/campaigns/*/pause' => Http::response(['id' => 'camp-1', 'status' => 2]),
            self::API.'/campaigns' => Http::response(['id' => 'camp-1', 'status' => 0]),
            self::API.'/leads/list' => Http::response(['items' => $leads, 'next_starting_after' => null]),
            self::API.'/leads' => Http::sequence()->push(['id' => 'lead-1'])->push(['id' => 'lead-2'])->push(['id' => 'lead-3']),
            self::API.'/emails*' => Http::response(['items' => $emails, 'next_starting_after' => null]),
            self::API.'/block-lists-entries' => Http::response(['id' => 'bl-1']),
        ]);
    }

    private function approved(string $name, string $email, ?string $follow1 = 'Day three note.'): Draft
    {
        $b = Business::create(['name' => $name, 'category' => 'Roofers', 'stage' => 'draft_ready']);
        $contact = $b->contacts()->create(['email' => $email, 'name' => 'Dana Lopez']);

        return $b->drafts()->create(['contact_id' => $contact->id, 'to_email' => $email, 'subject' => 'your site on phones',
            'body' => "Hi Dana,\n\nYour site doesn't resize on phones.", 'follow_up_1' => $follow1, 'status' => 'approved', 'reviewed_at' => now()]);
    }

    public function test_nothing_can_start_until_the_checklist_is_done(): void
    {
        config(['services.instantly.key' => null, 'cloyne.outreach.mailing_address' => null]);
        $this->get('/campaigns')->assertOk()->assertSee('Before anything sends')->assertSee('0 of 4 done')->assertSee('Not yet');

        config(['services.instantly.key' => 'inst-key', 'cloyne.outreach.mailing_address' => 'x']);
        $this->fakeInstantly([['email' => 'a@getlaiqdigital.com', 'status' => 1, 'warmup_status' => 1, 'stat_warmup_score' => 41]]);
        $this->post('/settings/sending/start')->assertSessionHasErrors('sending');
        $this->assertFalse(Sender::isOn());
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/campaigns'));
    }

    public function test_owner_starts_sending_which_creates_a_safe_campaign(): void
    {
        $this->fakeInstantly();
        $this->get('/campaigns')->assertSee('4 of 4 done')->assertSee('aubre@getlaiqdigital.com');

        $this->post('/settings/sending/start')->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertTrue(Sender::isOn());
        $this->assertSame('camp-1', Sender::campaignId());
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/campaigns')) {
                return false;
            }
            $steps = $r['sequences'][0]['steps'];

            return $r['stop_on_reply'] === true && $r['text_only'] === true && $r['link_tracking'] === false
                && count($steps) === 3 && $steps[0]['variants'][0]['subject'] === '{{cloyne_subject}}'
                && $steps[1]['variants'][0]['subject'] === '' && $r['daily_limit'] === 2
                && $r['campaign_schedule']['schedules'][0]['days']['0'] === false;
        });
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/campaigns/camp-1/activate'));

        $this->actingAs(User::factory()->role('reviewer')->create());
        $this->post('/settings/sending/stop')->assertForbidden();
    }

    public function test_approved_emails_go_out_within_the_daily_limit_with_footer_and_follow_ups(): void
    {
        $this->fakeInstantly();
        Setting::put('sending_on', true);
        Setting::put('instantly_campaign', 'camp-1');
        $first = $this->approved('Sunshine Roofing', 'dana@sunshine.example');
        $this->approved('Second Roofing', 'b@second.example', null);
        $this->approved('Third Roofing', 'c@third.example');

        $this->assertSame(2, app(Sender::class)->send()); // 1 inbox × 2 a day

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/leads') || $r['email'] !== 'dana@sunshine.example') {
                return false;
            }
            $v = $r['custom_variables'];

            return $r['campaign'] === 'camp-1' && $r['first_name'] === 'Dana' && $v['cloyne_subject'] === 'your site on phones'
                && str_contains($v['cloyne_body_1'], 'resize on phones.<br>')
                && str_contains($v['cloyne_body_1'], 'Miami, FL 33101')
                && str_contains($v['cloyne_body_1'], 'won&#039;t email you again')
                && str_contains($v['cloyne_body_2'], 'Day three note.');
        });
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/leads') && $r['email'] === 'b@second.example'
            && str_contains($r['custom_variables']['cloyne_body_2'], 'top of your inbox'));

        $this->assertSame(['queued', 'lead-1'], [$first->fresh()->send_status, $first->fresh()->instantly_lead_id]);
        $this->assertSame('emailed', $first->business->fresh()->stage);
        $this->assertSame(0, app(Sender::class)->send()); // today's limit is used up
    }

    public function test_nothing_sends_while_off(): void
    {
        $this->fakeInstantly();
        Setting::put('instantly_campaign', 'camp-1');
        $this->approved('Sunshine Roofing', 'dana@sunshine.example');

        $this->assertSame(0, app(Sender::class)->send());
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/leads'));
    }

    public function test_sync_brings_back_bounces_opt_outs_and_replies(): void
    {
        Setting::put('instantly_campaign', 'camp-1');
        $bounced = $this->approved('Bounce Roofing', 'x@bounce.example');
        $unsub = $this->approved('Unsub Roofing', 'u@unsub.example');
        $happy = $this->approved('Happy Roofing', 'h@happy.example');
        $polite = $this->approved('Polite Roofing', 'p@polite.example');
        foreach ([$bounced, $unsub, $happy, $polite] as $i => $d) {
            $d->update(['send_status' => 'queued', 'queued_at' => now(), 'instantly_lead_id' => "lead-{$i}"]);
        }

        $this->fakeInstantly(leads: [
            ['id' => 'lead-0', 'status' => -1, 'payload' => ['cloyne_draft' => $bounced->id]],
            ['id' => 'lead-1', 'status' => -2, 'payload' => ['cloyne_draft' => $unsub->id]],
        ], emails: [
            ['id' => 'em-1', 'lead' => 'h@happy.example', 'subject' => 'Re: your site on phones', 'body' => ['text' => "Sure, Tuesday works.\n\nOn Mon, Aubre wrote:\n> no thanks"], 'is_auto_reply' => 0, 'timestamp_email' => now()->toIso8601String()],
            ['id' => 'em-2', 'lead' => 'p@polite.example', 'subject' => 'Re: your site', 'body' => ['text' => 'No thanks, we are all set.'], 'is_auto_reply' => 0, 'timestamp_email' => now()->toIso8601String()],
            ['id' => 'em-3', 'lead' => 'h@happy.example', 'subject' => 'Out of office', 'body' => ['text' => 'I am away until Monday.'], 'is_auto_reply' => 1, 'timestamp_email' => now()->toIso8601String()],
        ]);

        $result = app(Sender::class)->sync();

        $this->assertSame(['updated' => 2, 'replies' => 3], $result);
        $this->assertSame('bounced', $bounced->fresh()->send_status);
        $this->assertSame('bounced', $bounced->contact->fresh()->email_status);
        $this->assertSame(['excluded', 'Asked not to be contacted'], [$unsub->business->fresh()->stage, $unsub->business->fresh()->exclude_reason]);
        $this->assertSame(['replied', 'replied'], [$happy->fresh()->send_status, $happy->business->fresh()->stage]);
        $this->assertSame('unsubscribed', $polite->fresh()->send_status);
        $this->assertSame('excluded', $polite->business->fresh()->stage);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/block-lists-entries') && $r['bl_value'] === 'p@polite.example');

        // The same replies again aren't saved twice.
        app(Sender::class)->sync();
        $this->assertSame(3, Reply::count());

        $this->get('/inbox')->assertOk()->assertSee('Sure, Tuesday works.')->assertSee('Opted out')->assertDontSee('I am away until Monday.');
        $this->get('/inbox?show=all')->assertSee('I am away until Monday.');
        $reply = Reply::where('instantly_email_id', 'em-1')->sole();
        $this->post('/inbox/'.$reply->id.'/read')->assertRedirect();
        $this->assertNotNull($reply->fresh()->read_at);
    }

    public function test_sending_pauses_itself_when_bounces_run_high(): void
    {
        $this->fakeInstantly();
        Setting::put('sending_on', true);
        Setting::put('instantly_campaign', 'camp-1');
        $b = Business::create(['name' => 'Many Roofing', 'category' => 'Roofers']);
        foreach (range(1, 50) as $i) {
            $b->drafts()->create(['to_email' => "x{$i}@a.example", 'subject' => 's', 'body' => 'b', 'status' => 'approved',
                'send_status' => $i <= 2 ? 'bounced' : 'queued', 'queued_at' => now()]);
        }

        app(Sender::class)->sync();

        $this->assertFalse(Sender::isOn());
        $this->assertStringContainsString('4.0% of last week', config('cloyne.settings.sending_paused_reason'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/campaigns/camp-1/pause'));
        $this->get('/campaigns')->assertSee('Paused by itself');
    }

    public function test_reply_opt_out_words(): void
    {
        $this->assertTrue(Reply::isOptOut('Please remove me from your list'));
        $this->assertTrue(Reply::isOptOut('STOP'));
        $this->assertTrue(Reply::isOptOut('Not interested, thanks'));
        $this->assertFalse(Reply::isOptOut("Yes! Can you call me Tuesday?\n\nOn Monday you wrote:\n> Not interested? Reply no thanks"));
        $this->assertFalse(Reply::isOptOut('Can you stop by the shop Friday?'));
    }

    public function test_review_queue_edits_follow_ups(): void
    {
        $draft = $this->approved('Sunshine Roofing', 'dana@sunshine.example');
        $draft->update(['status' => 'pending']);

        $this->get('/review?draft='.$draft->id)->assertSee('Follow-ups')->assertSee('Day three note.');
        $this->put('/review/'.$draft->id, ['subject' => 'your site on phones', 'body' => $draft->body, 'follow_up_1' => 'New day 3.', 'follow_up_2' => ''])->assertRedirect();

        $this->assertSame(['New day 3.', null, true], [$draft->fresh()->follow_up_1, $draft->fresh()->follow_up_2, $draft->fresh()->edited]);
        $this->assertStringContainsString('Last note from me', $draft->fresh()->followUps()[1]);
    }

    public function test_send_command_is_quiet_without_a_key(): void
    {
        config(['services.instantly.key' => null]);
        $this->artisan('cloyne:send')->expectsOutput('Instantly is not connected.')->assertSuccessful();
    }
}
