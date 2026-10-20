<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Draft;
use App\Models\User;
use App\Services\Drafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.anthropic.key' => 'test-key',
            'services.anthropic.draft_model' => 'claude-sonnet-5-5',
            'cloyne.selling' => ['website', 'ads_crm', 'scale'],
            'cloyne.outreach.booking_url' => 'https://book.example/call',
            'cloyne.outreach.mailing_address' => null,
        ]);
    }

    private function hotLead(string $name = 'Bright Smile Dental', ?string $email = 'hello@brightsmile.example', int $points = 70): Business
    {
        $lead = Business::create(['name' => $name, 'category' => 'Dentists', 'city' => 'Dallas', 'state' => 'TX', 'website' => 'https://brightsmile.example']);
        if ($email) {
            $lead->contacts()->create(['email' => $email, 'name' => 'Dana Lopez']);
        }
        $lead->recordAudit([
            ['title' => 'Mobile-friendly', 'detail' => 'No viewport tag; layout won’t adapt to phones', 'passed' => false, 'points' => $points],
        ], 'Your site doesn’t resize on phones.', 'website');

        return $lead->fresh();
    }

    private function fakeClaude(array $input): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'tool_use', 'name' => 'record_email', 'input' => $input]],
            ]),
        ]);
    }

    private function goodEmail(): array
    {
        return [
            'angle' => 'Site doesn’t work on phones',
            'subject' => 'your site on phones',
            'body' => "Hi Dana,\n\nI looked at brightsmile.example on my phone and the page doesn't resize, so patients have to pinch and zoom.\n\nWe rebuild sites like this so they work on every phone.\n\nWorth a 15-minute call?\nhttps://book.example/call\n\nBest,\nAubre’\nCEO, LaiQ Digital",
        ];
    }

    public function test_drafter_writes_an_email_from_the_audit(): void
    {
        $this->fakeClaude($this->goodEmail());
        $lead = $this->hotLead();

        $draft = app(Drafter::class)->write($lead);

        $this->assertSame('pending', $draft->status);
        $this->assertSame('hello@brightsmile.example', $draft->to_email);
        $this->assertSame('your site on phones', $draft->subject);
        $this->assertSame('website', $draft->package);
        $this->assertStringEndsWith('https://book.example/call', $draft->body); // sign-off removed
        $this->assertSame('draft_ready', $lead->fresh()->stage);
        $this->assertSame([], array_values(array_filter($draft->warnings(), fn ($w) => ! str_contains($w, 'Long'))));

        Http::assertSent(function ($request) {
            $system = $request['system'][0]['text'];

            return $request['model'] === 'claude-sonnet-5-5'
                && $request['tool_choice']['name'] === 'record_email'
                && str_contains($system, 'https://book.example/call')
                && str_contains($request['messages'][0]['content'], 'MISSING Mobile-friendly');
        });
    }

    public function test_warnings_flag_prices_promises_and_fake_familiarity(): void
    {
        $draft = new Draft(['subject' => 'hi', 'body' => 'I was driving by. We guarantee results for $1,500/mo.', 'to_email' => 'a@b.example']);

        $warnings = implode(' | ', $draft->warnings());
        $this->assertStringContainsString('price', $warnings);
        $this->assertStringContainsString('Promises results', $warnings);
        $this->assertStringContainsString('Fakes familiarity', $warnings);
    }

    public function test_draftable_only_includes_hot_leads_with_an_email_and_no_draft(): void
    {
        $hot = $this->hotLead('Hot');
        $this->hotLead('No email', null);
        $this->hotLead('Cold', 'cold@x.example', 20);
        $taken = $this->hotLead('Has draft', 'd@x.example');
        $taken->drafts()->create(['status' => 'pending']);

        $this->assertSame(['Hot'], Business::draftable()->pluck('name')->all());
    }

    public function test_write_many_queues_drafts_for_hot_leads(): void
    {
        $this->fakeClaude($this->goodEmail());
        $this->hotLead('A', 'a@x.example');
        $this->hotLead('B', 'b@x.example');

        $this->post('/review/write')->assertRedirect();

        // The test queue runs jobs right away.
        $this->assertSame(2, Draft::status('pending')->count());
        $this->post('/review/write')->assertSessionHas('status', 'Every hot lead with an email already has a draft.');
    }

    public function test_review_screen_shows_the_draft_and_missing_address(): void
    {
        $this->fakeClaude($this->goodEmail());
        $draft = app(Drafter::class)->write($this->hotLead());

        $this->get('/review')
            ->assertOk()
            ->assertSee('Bright Smile Dental')
            ->assertSee('your site on phones')
            ->assertSee('Add a mailing address before sending')
            ->assertSee('Approve');
        $this->get("/review?draft={$draft->id}")->assertOk();
    }

    public function test_approve_saves_edits_and_records_who_approved(): void
    {
        $this->fakeClaude($this->goodEmail());
        $draft = app(Drafter::class)->write($this->hotLead());

        $this->put("/review/{$draft->id}/approve", ['subject' => 'your website on phones', 'body' => 'Hi Dana, edited.'])
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame('approved', $draft->status);
        $this->assertSame('your website on phones', $draft->subject);
        $this->assertTrue($draft->edited);
        $this->assertSame(auth()->id(), $draft->reviewed_by);
    }

    public function test_skip_and_reopen(): void
    {
        $this->fakeClaude($this->goodEmail());
        $draft = app(Drafter::class)->write($lead = $this->hotLead());

        $this->post("/review/{$draft->id}/skip")->assertRedirect();
        $this->assertSame('skipped', $draft->fresh()->status);
        $this->assertSame('audited', $lead->fresh()->stage);
        $this->assertSame(1, Business::draftable()->count()); // skipped leads can be drafted again

        $this->post("/review/{$draft->id}/reopen")->assertRedirect();
        $this->assertSame('pending', $draft->fresh()->status);
        $this->assertSame('draft_ready', $lead->fresh()->stage);
    }

    public function test_a_failed_claude_call_marks_the_draft_failed(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $lead = $this->hotLead();

        $this->post("/leads/{$lead->id}/draft")->assertSessionHasErrors('draft');

        $this->assertSame('failed', $lead->drafts()->sole()->status);
    }

    public function test_writing_needs_an_audit_first(): void
    {
        $lead = Business::create(['name' => 'Fresh', 'category' => 'Gyms']);

        $this->post("/leads/{$lead->id}/draft")->assertSessionHasErrors(['draft' => 'Audit this lead first, so the email can be about its real gaps.']);
        $this->assertSame(0, Draft::count());
    }

    public function test_viewers_can_read_drafts_but_not_act(): void
    {
        $this->fakeClaude($this->goodEmail());
        $draft = app(Drafter::class)->write($this->hotLead());
        $this->actingAs(User::factory()->role('viewer')->create());

        $this->get('/review')->assertOk()->assertSee('your site on phones')->assertDontSee('>Approve<', false);
        $this->post("/review/{$draft->id}/approve")->assertForbidden();
        $this->post("/review/{$draft->id}/skip")->assertForbidden();
        $this->post('/review/write')->assertForbidden();
    }

    public function test_deleting_a_lead_deletes_its_drafts(): void
    {
        $this->fakeClaude($this->goodEmail());
        $draft = app(Drafter::class)->write($lead = $this->hotLead());

        $this->delete("/leads/{$lead->id}");

        $this->assertNull($draft->fresh());
    }
}
