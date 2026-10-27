<?php

namespace Tests\Feature;

use App\Jobs\HandleReply;
use App\Models\Business;
use App\Models\Reply;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReplyAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReplyAgentTest extends TestCase
{
    use RefreshDatabase;

    private function reply(string $body, array $extra = []): Reply
    {
        $b = Business::firstOrCreate(['name' => 'Sunshine Roofing'], ['category' => 'Roofers', 'stage' => 'replied']);

        return Reply::create($extra + ['business_id' => $b->id, 'instantly_email_id' => 'em-'.uniqid(), 'from_email' => 'dana@sunshine.example',
            'eaccount' => 'aubre@getlaiqdigital.com', 'subject' => 'Re: your site on phones', 'body' => $body, 'received_at' => now()]);
    }

    private function fakeClaude(array $input): void
    {
        config(['services.anthropic.key' => 'test-key', 'services.instantly.key' => 'inst-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'record_reply', 'input' => $input]], 'usage' => ['input_tokens' => 2000, 'output_tokens' => 200]]),
            'api.instantly.ai/api/v2/emails/reply' => Http::response(['id' => 'sent-1']),
        ]);
    }

    public function test_interested_reply_gets_an_answer_from_the_fact_sheet_only(): void
    {
        $this->fakeClaude(['category' => 'interested', 'needs_person' => false, 'answer' => "Hi Dana,\n\nGreat. Pick a time here:\nhttps://admin.laiqdigital.com/booking/laiq-digital/strategy-call"]);
        $reply = $this->reply('Sure, sounds good. How do we set it up?');

        app(ReplyAgent::class)->handle($reply);

        $reply->refresh();
        $this->assertSame(['interested', 'drafted', false], [$reply->category, $reply->answer_status, $reply->needs_person]);
        Http::assertSent(fn (Request $r) => str_contains($r['system'][0]['text'] ?? '', 'Who pays for the ads?')
            && str_contains($r['system'][0]['text'], 'Prices, quotes or discounts') && $r['tool_choice']['name'] === 'record_reply');
        $this->assertDatabaseHas('api_costs', ['purpose' => 'reply']);
    }

    public function test_price_question_goes_to_a_person_with_no_answer(): void
    {
        $this->fakeClaude(['category' => 'question', 'needs_person' => true, 'handoff_reason' => 'Asks about price.', 'answer' => 'It is $500.']);
        $reply = $this->reply('How much does it cost?');

        app(ReplyAgent::class)->handle($reply);

        $reply->refresh();
        $this->assertSame([true, null, null, 'Asks about price.'], [$reply->needs_person, $reply->answer, $reply->answer_status, $reply->handoff_reason]);
        $this->get('/inbox')->assertSee('Needs you')->assertSee('Asks about price.')->assertSee('Your answer');
    }

    public function test_third_reply_and_opt_outs_and_auto_replies(): void
    {
        $this->fakeClaude(['category' => 'question', 'needs_person' => false, 'answer' => 'Yes, month to month.']);
        $this->reply('First', ['received_at' => now()->subDays(2)]);
        $this->reply('Second', ['received_at' => now()->subDay()]);
        $third = $this->reply('Third: is there a contract?');
        app(ReplyAgent::class)->handle($third);
        $this->assertSame([true, null], [$third->fresh()->needs_person, $third->fresh()->answer]);
        $this->assertStringContainsString('Third reply', $third->fresh()->handoff_reason);

        $stop = $this->reply('Remove me', ['opt_out' => true]);
        app(ReplyAgent::class)->handle($stop);
        $this->assertSame(['opt_out', 'skipped'], [$stop->fresh()->category, $stop->fresh()->answer_status]);

        $ooo = $this->reply('Out of office', ['is_auto_reply' => true]);
        app(ReplyAgent::class)->handle($ooo);
        $this->assertSame('skipped', $ooo->fresh()->answer_status);
        Http::assertSentCount(1); // only the third reply asked Claude
    }

    public function test_a_person_approves_and_sends_the_answer_in_the_thread(): void
    {
        $this->fakeClaude(['category' => 'interested', 'needs_person' => false, 'answer' => 'Pick a time.']);
        $reply = $this->reply('Interested!', ['answer' => 'Pick a time.', 'answer_status' => 'drafted', 'category' => 'interested']);

        $this->get('/inbox?show=todo')->assertSee('AI answer, from the fact sheet')->assertSee('Approve and send');
        $this->post('/inbox/'.$reply->id.'/send', ['answer' => 'Pick a time that suits you.'])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/emails/reply') && $r['reply_to_uuid'] === $reply->instantly_email_id
            && $r['eaccount'] === 'aubre@getlaiqdigital.com' && $r['subject'] === 'Re: your site on phones'
            && str_contains($r['body']['text'], "Pick a time that suits you.\n\nAubre’ Cyriaque"));
        $this->assertSame('sent', $reply->fresh()->answer_status);
        $this->get('/inbox')->assertSee('Answered');
    }

    public function test_handled_booked_and_permissions(): void
    {
        $reply = $this->reply('Call me Tuesday');
        $this->post('/inbox/'.$reply->id.'/booked')->assertRedirect();
        $this->assertSame('booked', $reply->business->fresh()->stage);
        $this->post('/inbox/'.$reply->id.'/handled');
        $this->assertSame('handled', $reply->fresh()->answer_status);

        $this->put('/settings/reply-agent', ['on' => 1]);
        $this->assertTrue(ReplyAgent::isOn());
        $this->actingAs(User::factory()->role('viewer')->create());
        $this->post('/inbox/'.$reply->id.'/send', ['answer' => 'x'])->assertForbidden();
        $this->put('/settings/reply-agent', ['on' => 0])->assertForbidden();
    }

    public function test_new_replies_are_handled_automatically_only_when_switched_on(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key', 'services.instantly.key' => 'inst-key']);
        Setting::put('instantly_campaign', 'camp-1');
        $email = fn ($id) => ['id' => $id, 'lead' => 'dana@sunshine.example', 'eaccount' => 'aubre@getlaiqdigital.com', 'subject' => 'Re: hi', 'body' => ['text' => 'Tell me more'], 'is_auto_reply' => 0, 'timestamp_email' => now()->toIso8601String()];
        Http::fake([
            'api.instantly.ai/api/v2/leads/list' => Http::response(['items' => []]),
            'api.instantly.ai/api/v2/emails*' => Http::sequence()->push(['items' => [$email('a')]])->push(['items' => [$email('b')]]),
            'api.instantly.ai/api/v2/accounts*' => Http::response(['items' => []]),
        ]);

        app(\App\Services\Sender::class)->sync();
        Queue::assertNothingPushed();
        $this->assertSame('aubre@getlaiqdigital.com', Reply::where('instantly_email_id', 'a')->value('eaccount'));

        Setting::put('reply_agent_on', true);
        app(\App\Services\Sender::class)->sync();
        Queue::assertPushed(HandleReply::class, 1);
    }
}
