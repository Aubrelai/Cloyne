<?php

namespace Tests\Feature;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Models\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    private function key(): string
    {
        return $this->post('/settings/costs-key')->getSession()->get('costs_key');
    }

    private function audited(Business $b, int $score, string $package): void
    {
        $b->recordAudit([['title' => 'No booking form', 'detail' => 'x', 'passed' => false, 'points' => $score]], 'Add online booking.', $package);
    }

    public function test_every_crm_route_needs_the_key(): void
    {
        $this->postJson('/api/crm/leads', ['leads' => []])->assertUnauthorized();
        $this->getJson('/api/crm/leads')->assertUnauthorized();
        $this->getJson('/api/crm/call-list')->assertUnauthorized();
        $this->postJson('/api/crm/calls', [])->assertUnauthorized();
    }

    public function test_crm_list_is_imported_audited_and_read_back_by_package(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key']);
        $existing = Business::create(['name' => 'Sunshine Roofing', 'category' => 'Roofers', 'city' => 'Dallas', 'phone' => '+12145550100']);
        $key = $this->key();

        $this->withToken($key)->postJson('/api/crm/leads', ['leads' => [
            ['crm_id' => 'c1', 'name' => 'Sunshine Roofing LLC', 'phone' => '(214) 555-0100', 'list' => 'Dallas roofers'],
            ['crm_id' => 'c2', 'name' => 'Bright Smile Dental', 'phone' => '305-555-0199', 'website' => 'brightsmile.example', 'city' => 'Miami', 'state' => 'fl', 'email' => 'Hi@BrightSmile.example'],
        ]])->assertOk()->assertJson(['received' => 2, 'added' => 1, 'matched' => 1, 'queued' => 2]);

        Queue::assertPushed(AuditBusiness::class, 2);
        $this->assertSame('c1', $existing->fresh()->crm_id);
        $this->assertSame('Dallas roofers', $existing->fresh()->crm_list);
        $dental = Business::where('crm_id', 'c2')->sole();
        $this->assertSame(['crm', 'FL', '+13055550199', 'hi@brightsmile.example'], [$dental->source, $dental->state, $dental->phone, $dental->contacts()->value('email')]);

        // Sending the same rows again doesn't duplicate or re-queue.
        $this->withToken($key)->postJson('/api/crm/leads', ['leads' => [['crm_id' => 'c2', 'name' => 'Bright Smile Dental']]])
            ->assertJson(['added' => 0, 'matched' => 1, 'queued' => 0]);

        $this->audited($dental, 72, 'ads_crm');
        $this->withToken($key)->getJson('/api/crm/leads?crm_ids=c2,c1')
            ->assertOk()
            ->assertJsonPath('leads.0.crm_id', 'c1')
            ->assertJsonPath('leads.0.status', 'auditing')
            ->assertJsonPath('leads.1.crm_id', 'c2')
            ->assertJsonPath('leads.1.status', 'audited')
            ->assertJsonPath('leads.1.score', 72)
            ->assertJsonPath('leads.1.hot', true)
            ->assertJsonPath('leads.1.package.short', 'Growth')
            ->assertJsonPath('leads.1.issues.0', 'No booking form')
            ->assertJsonPath('leads.1.pitch', 'Add online booking.');

        // CRM leads are the callers' to work: never drafted for email.
        $this->assertFalse(Business::draftable()->whereKey($dental->id)->exists());
    }

    public function test_call_list_sends_new_hot_leads_and_takes_call_results_back(): void
    {
        $key = $this->key();
        $hot = Business::create(['name' => 'Hot Plumbing', 'category' => 'Plumbers', 'phone' => '+17135550123']);
        $this->audited($hot, 80, 'scale');
        $cold = Business::create(['name' => 'Cold Gym', 'category' => 'Gyms', 'phone' => '+17135550124']);
        $this->audited($cold, 30, 'website');
        $noPhone = Business::create(['name' => 'No Phone Spa', 'category' => 'Spas']);
        $this->audited($noPhone, 90, 'scale');

        $this->withToken($key)->getJson('/api/crm/call-list')
            ->assertOk()->assertJsonCount(1, 'leads')
            ->assertJsonPath('leads.0.name', 'Hot Plumbing')
            ->assertJsonPath('leads.0.package.short', 'Scale');

        $this->withToken($key)->postJson('/api/crm/call-list/added', ['added' => [['cloyne_id' => $hot->id, 'crm_id' => 'p9', 'list' => 'Cloyne hot leads']]])
            ->assertJson(['linked' => 1]);
        $this->withToken($key)->getJson('/api/crm/call-list')->assertJsonCount(0, 'leads');

        $this->withToken($key)->postJson('/api/crm/calls', ['calls' => [
            ['crm_id' => 'p9', 'crm_call_id' => 'call-1', 'outcome' => 'no_answer', 'caller' => 'Jay'],
            ['crm_id' => 'p9', 'crm_call_id' => 'call-2', 'outcome' => 'booked', 'notes' => 'Tuesday 2pm'],
            ['crm_id' => 'nope', 'outcome' => 'voicemail'],
        ]])->assertOk()->assertJson(['saved' => 2, 'unknown' => ['nope']]);

        // Same call sent twice is saved once.
        $this->withToken($key)->postJson('/api/crm/calls', ['calls' => [['crm_id' => 'p9', 'crm_call_id' => 'call-2', 'outcome' => 'booked', 'notes' => 'Tuesday 2pm']]]);
        $this->assertSame(2, Call::count());
        $this->assertSame('booked', $hot->fresh()->stage);

        $this->withToken($key)->postJson('/api/crm/calls', ['calls' => [['cloyne_id' => $cold->id, 'outcome' => 'not_interested']]]);
        $this->assertSame(['excluded', 'Not interested'], [$cold->fresh()->stage, $cold->fresh()->exclude_reason]);

        $this->withToken($key)->postJson('/api/crm/calls', ['calls' => [['crm_id' => 'p9', 'outcome' => 'made_up']]])->assertStatus(422);

        $this->get('/leads?lead='.$hot->id)->assertOk()->assertSee('Calls')->assertSee('Tuesday 2pm')->assertSee('Booked');
        $this->get('/settings')->assertSee('CRM connection')->assertSee('1 lead on CRM call lists', false)->assertSee('3 calls logged', false);
    }

    public function test_crm_reads_only_the_emails_of_cloyne_logins(): void
    {
        $this->getJson('/api/crm/users')->assertUnauthorized();
        \App\Models\User::factory()->role('viewer')->create(['email' => 'Jay@LaiqDigital.com']);
        $key = $this->key();

        $response = $this->withToken($key)->getJson('/api/crm/users')->assertOk();
        $users = $response->json('users');

        $this->assertContains(['email' => 'jay@laiqdigital.com'], $users);
        $this->assertCount(\App\Models\User::count(), $users);
        foreach ($users as $user) {
            $this->assertSame(['email'], array_keys($user));
        }
        $this->assertStringNotContainsString('password', $response->getContent());
    }
}
