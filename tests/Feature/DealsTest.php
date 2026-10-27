<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Reply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsTest extends TestCase
{
    use RefreshDatabase;

    private function key(): string
    {
        return $this->post('/settings/costs-key')->getSession()->get('costs_key');
    }

    private function lead(string $name, array $extra = []): Business
    {
        $b = Business::create($extra + ['name' => $name, 'category' => 'Roofers', 'phone' => '+15615550100']);
        $b->recordAudit([['title' => 'No booking form', 'detail' => 'No way to book', 'passed' => false, 'points' => 70]], 'Add booking.', 'ads_crm');

        return $b->fresh();
    }

    public function test_bookings_from_calls_and_replies_reach_the_crm_with_their_history(): void
    {
        $key = $this->key();
        $called = $this->lead('Called Roofing', ['crm_id' => 'c1']);
        $emailed = $this->lead('Emailed Roofing');
        $draft = $emailed->drafts()->create(['to_email' => 'o@emailed.example', 'subject' => 'your site', 'body' => 'Hi, no booking form.', 'status' => 'approved', 'send_status' => 'replied', 'queued_at' => now()->subDay()]);
        $reply = Reply::create(['business_id' => $emailed->id, 'draft_id' => $draft->id, 'instantly_email_id' => 'em-1', 'from_email' => 'o@emailed.example', 'subject' => 'Re: your site', 'body' => 'Yes, call me.', 'received_at' => now()]);
        $this->lead('Not Booked Gym');

        $this->withToken($key)->postJson('/api/crm/calls', ['calls' => [['crm_id' => 'c1', 'outcome' => 'booked', 'notes' => 'Thu 10am', 'caller' => 'Jay']]])->assertOk();
        $this->post('/inbox/'.$reply->id.'/booked')->assertRedirect();

        $this->assertSame(['booked', 'call', 'booked'], [$called->fresh()->stage, $called->fresh()->booked_via, $called->fresh()->deal_stage]);
        $this->assertSame('email', $emailed->fresh()->booked_via);

        $res = $this->withToken($key)->getJson('/api/crm/booked')->assertOk()->assertJsonCount(2, 'booked');
        $byName = collect($res->json('booked'))->keyBy('name');
        $this->assertSame('call', $byName['Called Roofing']['booked_via']);
        $this->assertSame('Thu 10am', $byName['Called Roofing']['calls'][0]['notes']);
        $this->assertSame(['sent', 'received'], array_column($byName['Emailed Roofing']['thread'], 'direction'));
        $this->assertSame('No booking form', $byName['Emailed Roofing']['audit'][0]['title']);
        $this->assertSame('Growth', $byName['Emailed Roofing']['package']['short']);
    }

    public function test_crm_reports_deal_stages_and_won_deals_become_clients(): void
    {
        $key = $this->key();
        $a = $this->lead('Won Roofing', ['crm_id' => 'c1']);
        $a->markBooked('call');
        $b = $this->lead('Lost Roofing');
        $b->markBooked('email');

        $this->withToken($key)->postJson('/api/crm/deals', ['deals' => [
            ['crm_id' => 'c1', 'crm_deal_id' => 'd-9', 'stage' => 'won', 'package' => 'Growth', 'value' => 1500, 'note' => 'Signed, starts Nov 1'],
            ['cloyne_id' => $b->id, 'stage' => 'lost', 'note' => 'Went with nephew'],
            ['cloyne_id' => 999999, 'stage' => 'showed'],
        ]])->assertOk()->assertJson(['saved' => 2, 'unknown' => [999999]]);

        $a->refresh();
        $this->assertSame(['won', 'won', 'ads_crm', '1500.00', 'd-9'], [$a->stage, $a->deal_stage, $a->deal_package, $a->deal_value, $a->crm_deal_id]);
        $this->assertSame(['lost', 'lost'], [$b->fresh()->stage, $b->fresh()->deal_stage]);

        $this->withToken($key)->postJson('/api/crm/deals', ['deals' => [['crm_id' => 'c1', 'stage' => 'won_big']]])->assertStatus(422);

        $this->get('/booked')->assertOk()->assertSee('Won Roofing')->assertSee('Won')->assertSee('$1,500/mo')->assertSee('Went with nephew')->assertSee('Email reply');
        $this->get('/')->assertSee('won <strong class="mono">$1,500/mo</strong>', false);

        // Clients and lost deals never go back on call lists.
        $this->assertFalse(Business::forCrmCallList()->whereKey($a->id)->exists());
    }

    public function test_deal_routes_need_the_key(): void
    {
        $this->getJson('/api/crm/booked')->assertUnauthorized();
        $this->postJson('/api/crm/deals', [])->assertUnauthorized();
    }
}
