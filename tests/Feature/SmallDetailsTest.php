<?php

namespace Tests\Feature;

use App\Jobs\AuditBusiness;
use App\Jobs\WriteDraft;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Draft;
use App\Models\SavedView;
use App\Models\Search;
use App\Models\Setting;
use App\Models\User;
use App\Services\Drafter;
use App\Services\LeadImporter;
use App\Support\BusinessName;
use App\Support\FriendlyError;
use App\Support\Phone;
use App\Support\WebAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/** The small details plan: clean data, Leads, Lead Finder, Review queue, Settings, Account. */
class SmallDetailsTest extends TestCase
{
    use RefreshDatabase;

    /* ---------- Clean data ---------- */

    public function test_phone_numbers_have_one_format(): void
    {
        $this->assertSame('+13055550100', Phone::normalize('(305) 555 0100'));
        $this->assertSame('+13055550100', Phone::normalize('+1 305-555-0100'));
        $this->assertSame('(305) 555-0100', Phone::display('3055550100'));
        $this->assertSame('+13055550100', Phone::dial('305.555.0100'));
        $this->assertNull(Phone::normalize(''));
    }

    public function test_business_names_read_like_a_person_wrote_them(): void
    {
        $this->assertSame('Bright Smile Dental', BusinessName::clean('BRIGHT SMILE DENTAL LLC'));
        $this->assertSame('Bright Smile Dental', BusinessName::clean('Dentist Dallas TX | Bright Smile Dental'));
        $this->assertSame('ABC HVAC & Plumbing', BusinessName::clean('ABC HVAC & PLUMBING, INC.'));
        $this->assertSame('Smith & Co.', BusinessName::clean('Smith & Co.'));
        $this->assertSame("Joe's Pizza", BusinessName::clean("Joe's Pizza"));
    }

    public function test_website_links_lose_tracking_bits(): void
    {
        $this->assertSame('https://www.bright-smile.com/?page=2', WebAddress::clean('www.Bright-Smile.com/?utm_source=gbp&page=2'));
        $this->assertSame('https://example.com', WebAddress::clean('http://example.com/'));
        $this->assertSame('bright-smile.com', WebAddress::host('https://www.bright-smile.com/dallas'));
    }

    public function test_system_and_builder_emails_are_ignored(): void
    {
        $this->assertTrue(Contact::usableEmail('dana@brightsmile.example'));
        $this->assertFalse(Contact::usableEmail('noreply@brightsmile.example'));
        $this->assertFalse(Contact::usableEmail('abc123@sentry.wixpress.com'));
        $this->assertFalse(Contact::usableEmail('logo@2x.png'));
        $this->assertFalse(Contact::usableEmail('not an email'));
    }

    public function test_importer_merges_duplicates_and_flags_chains(): void
    {
        Business::create(['name' => 'Bright Smile Dental', 'category' => 'Dentists', 'city' => 'Dallas', 'phone' => '+12145550100']);
        $search = Search::create(['category' => 'dentists', 'area' => 'Dallas, TX', 'max_results' => 100]);

        $chain = fn ($n) => ['title' => "Smile Chain #{$n}", 'city' => "City {$n}", 'placeId' => "chain{$n}", 'website' => 'https://www.smilechain.example/loc'.$n];
        $result = app(LeadImporter::class)->import($search, [
            ['title' => 'Bright Smile Dental (2nd listing)', 'city' => 'Dallas', 'placeId' => 'dup', 'phone' => '(214) 555-0100'],
            $chain(1), $chain(2), $chain(3), $chain(4),
            ['title' => 'Uses Facebook', 'city' => 'A', 'placeId' => 'fb1', 'website' => 'https://facebook.com/a'],
        ]);

        $this->assertSame(1, $result['existing']);
        $this->assertSame(5, $result['added']);
        $this->assertSame(4, Business::where('is_chain', true)->count());
        $this->assertFalse(Business::where('place_id', 'fb1')->sole()->is_chain);
        $this->assertSame('smilechain.example', Business::where('place_id', 'chain1')->sole()->website_host);
    }

    public function test_failures_read_as_plain_sentences(): void
    {
        $this->assertSame("Couldn't reach Claude. Check the internet connection and try again.", FriendlyError::message(new ConnectionException('cURL error 28')));
        $this->assertSame('Your Claude API account is out of credits.', FriendlyError::message(new RuntimeException('Your Claude API account is out of credits.')));
    }

    /* ---------- Leads ---------- */

    public function test_excluding_keeps_the_lead_but_hides_it(): void
    {
        $lead = Business::create(['name' => 'Big Chain Dental', 'category' => 'Dentists']);
        Business::create(['name' => 'Keep Dental', 'category' => 'Dentists']);

        $this->post("/leads/{$lead->id}/exclude", ['reason' => 'Chain or franchise'])
            ->assertSessionHas('undo');

        $lead->refresh();
        $this->assertSame('excluded', $lead->stage);
        $this->assertSame('Chain or franchise', $lead->exclude_reason);
        $this->get('/booked'); // shows (and uses up) the "Excluded…" message
        $this->get('/leads?category=Dentists')->assertSee('Keep Dental')->assertDontSee('Big Chain Dental');
        $this->get('/leads?stage=excluded')->assertSee('Big Chain Dental');

        $this->post("/leads/{$lead->id}/include");
        $this->assertSame('new', $lead->fresh()->stage);
        $this->assertNull($lead->fresh()->exclude_reason);
    }

    public function test_bulk_actions_on_ticked_leads(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key']);
        $a = Business::create(['name' => 'A', 'category' => 'Gyms']);
        $b = Business::create(['name' => 'B', 'category' => 'Gyms']);

        $this->post('/leads/audit-selected', ['ids' => [$a->id, $b->id]])->assertRedirect();
        Queue::assertPushed(AuditBusiness::class, 2);

        $b->contacts()->create(['email' => 'b@gym.example']);
        $b->recordAudit([['title' => 'Website', 'detail' => 'None', 'passed' => false, 'points' => 70]], 'No site', 'website');
        $this->post('/leads/write-selected', ['ids' => [$a->id, $b->id]])->assertSessionHas('status');
        Queue::assertPushed(WriteDraft::class, 1);

        $this->post('/leads/exclude', ['ids' => [$a->id], 'reason' => 'Not a fit']);
        $this->assertSame('excluded', $a->fresh()->stage);
    }

    public function test_leads_sort_by_name_and_page_size(): void
    {
        Business::create(['name' => 'Zebra Gym', 'category' => 'Gyms', 'score' => 90]);
        Business::create(['name' => 'Alpha Gym', 'category' => 'Gyms', 'score' => 10]);

        $this->get('/leads?sort=name&dir=asc')->assertSeeInOrder(['Alpha Gym', 'Zebra Gym']);
        $this->get('/leads?sort=score&dir=desc')->assertSeeInOrder(['Zebra Gym', 'Alpha Gym']);
        $this->get('/leads?per=100')->assertOk();
    }

    public function test_leads_remembers_the_last_filters_until_cleared(): void
    {
        Business::create(['name' => 'A', 'category' => 'Gyms']);

        $this->get('/leads?category=Gyms')->assertOk();
        $this->get('/leads')->assertRedirect(route('leads', ['category' => 'Gyms']));
        $this->get('/leads?clear=1')->assertRedirect(route('leads'));
        $this->get('/leads')->assertOk();
    }

    public function test_saved_views_are_personal(): void
    {
        $this->post('/leads/views', ['name' => 'Hot gyms', 'category' => 'Gyms', 'score' => 'hot'])->assertRedirect();
        $view = SavedView::sole();
        $this->assertSame(['category' => 'Gyms', 'score' => 'hot'], $view->filters);
        $this->get('/leads?category=Gyms')->assertSee('Hot gyms');

        $this->actingAs(User::factory()->role('viewer')->create());
        $this->delete("/leads/views/{$view->id}")->assertForbidden();
        $this->post('/leads/views', ['name' => 'Mine', 'state' => 'FL'])->assertRedirect();
        $this->assertSame(2, SavedView::count());
    }

    public function test_score_shows_where_the_points_came_from(): void
    {
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas']);
        $lead->recordAudit([
            ['title' => 'Mobile-friendly', 'detail' => 'No viewport', 'passed' => false, 'points' => 25],
            ['title' => 'Online booking', 'detail' => 'None', 'passed' => false, 'points' => 20],
        ]);

        $this->get('/leads')->assertSee('Mobile-friendly +25', false);
    }

    public function test_lead_profile_shows_phone_link_maps_and_scan(): void
    {
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas', 'phone' => '+13055550100', 'place_id' => 'abc']);
        $audit = $lead->recordAudit([['title' => 'Website', 'detail' => 'x', 'passed' => true, 'points' => 0]]);
        $audit->update(['signals' => ['reachable' => true, 'meta_pixel' => false, 'emails' => []]]);

        $this->get("/leads?lead={$lead->id}")
            ->assertSee('tel:+13055550100', false)
            ->assertSee('(305) 555-0100')
            ->assertSee('Open in Google Maps')
            ->assertSee('Show what the scan saw');
    }

    /* ---------- Lead Finder ---------- */

    public function test_repeat_search_asks_first(): void
    {
        config(['services.apify.token' => 'test-token']);
        Http::fake(['api.apify.com/*' => Http::response(['data' => ['id' => 'r2', 'defaultDatasetId' => 'd2']], 201)]);
        Search::create(['category' => 'Dentists', 'area' => 'Dallas, TX', 'max_results' => 100, 'status' => 'done', 'found' => 84]);

        $this->post('/lead-finder/searches', ['category' => 'dentists', 'area' => 'Dallas, TX', 'max_results' => 100])
            ->assertSessionHas('repeat');
        $this->assertSame(1, Search::count());

        $this->post('/lead-finder/searches', ['category' => 'dentists', 'area' => 'Dallas, TX', 'max_results' => 100, 'repeat_ok' => 1]);
        $this->assertSame(2, Search::count());
    }

    public function test_run_a_search_again(): void
    {
        config(['services.apify.token' => 'test-token']);
        Http::fake(['api.apify.com/*' => Http::response(['data' => ['id' => 'r2', 'defaultDatasetId' => 'd2']], 201)]);
        $search = Search::create(['category' => 'Gyms', 'area' => 'Miami, FL', 'max_results' => 250, 'status' => 'done']);

        $this->post("/lead-finder/searches/{$search->id}/again")->assertRedirect('/lead-finder');

        $this->assertSame(250, Search::latest('id')->first()->max_results);
        $this->assertSame(2, Search::where('area', 'Miami, FL')->count());
    }

    /* ---------- Review queue ---------- */

    private function draftFor(Business $lead): Draft
    {
        return $lead->drafts()->create(['status' => 'pending', 'subject' => 'your site on phones', 'to_email' => 'dana@glow.example',
            'body' => "Hi Dana,\n\nYour site doesn't resize on phones, so visitors pinch and zoom.\n\nWorth a quick call?"]);
    }

    public function test_skip_offers_undo_and_counts_today(): void
    {
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas', 'score' => 70]);
        $draft = $this->draftFor($lead);

        $this->post("/review/{$draft->id}/skip")->assertSessionHas('undo', ['url' => route('drafts.reopen', $draft), 'label' => 'Undo']);
        $this->get('/review?status=skipped')->assertSee('you reviewed 1 today');
    }

    public function test_claim_check_matches_the_audit(): void
    {
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas']);
        $lead->recordAudit([['title' => 'Mobile-friendly', 'detail' => 'No viewport tag', 'passed' => false, 'points' => 30]]);
        $draft = $this->draftFor($lead->fresh());

        $this->assertSame('Mobile-friendly', $draft->claimedFinding()['title']);

        $draft->update(['body' => 'We help businesses grow.', 'subject' => 'hello']);
        $this->assertNull($draft->fresh()->claimedFinding());
    }

    public function test_quick_rewrite_sends_the_previous_version(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'record_email',
            'input' => ['angle' => 'Phones', 'subject' => 'shorter one', 'body' => 'Hi Dana, short.']]]])]);
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas']);
        $lead->contacts()->create(['email' => 'dana@glow.example']);
        $lead->recordAudit([['title' => 'Mobile-friendly', 'detail' => 'No viewport tag', 'passed' => false, 'points' => 70]], 'Phones', 'website');
        $draft = $this->draftFor($lead);

        $this->post("/review/{$draft->id}/rewrite", ['style' => 'shorter'])->assertSessionHas('status', 'Shorter version written.');

        $this->assertSame('shorter one', $draft->fresh()->subject);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Previous version')
            && str_contains($request['messages'][0]['content'], Drafter::REWRITES['shorter']));
    }

    /* ---------- Settings and Account ---------- */

    public function test_owner_edits_email_rules_on_screen(): void
    {
        $this->put('/settings/outreach', [
            'booking_url' => 'https://book.example/call',
            'mailing_address' => 'LaiQ Digital, 1 Main St, Miami, FL 33101',
            'tone' => 'Warm and short.',
            'never' => "Mention prices\n- Promise results",
            'min_score' => 65,
            'max_words' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertSame('LaiQ Digital, 1 Main St, Miami, FL 33101', config('cloyne.outreach.mailing_address'));
        $this->assertSame(['Mention prices', 'Promise results'], json_decode(Setting::find('never')->value, true));

        // A fresh load of settings (as the next request or the worker would do) picks them up.
        config(['cloyne.outreach.min_score' => 60]);
        Setting::applyToConfig();
        $this->assertSame(65, config('cloyne.outreach.min_score'));
        $this->assertTrue(Draft::readyToSend());

        $this->delete('/settings/outreach');
        $this->assertNull(Setting::find('tone'));
    }

    public function test_packages_on_sale_switch_on_screen(): void
    {
        $this->put('/settings/selling', ['selling' => ['website']])->assertSessionHas('status');
        $this->assertSame(['website'], config('cloyne.selling'));

        $this->put('/settings/selling', ['selling' => ['not_a_package']])->assertSessionHasErrors(null, null, 'selling');
    }

    public function test_reviewers_cannot_change_settings(): void
    {
        $this->actingAs(User::factory()->role('reviewer')->create());

        $this->put('/settings/selling', ['selling' => ['website']])->assertForbidden();
        $this->put('/settings/outreach', ['tone' => 'x', 'min_score' => 60, 'max_words' => 100])->assertForbidden();
        $this->post('/settings/test/claude')->assertForbidden();
        $this->get('/settings')->assertOk()->assertSee('Only the Owner can change these.');
    }

    public function test_connection_test_buttons(): void
    {
        config(['services.anthropic.key' => 'test-key', 'services.apify.token' => 'test-token']);
        Http::fake([
            'api.anthropic.com/v1/models*' => Http::response(['data' => []]),
            'api.apify.com/v2/users/me' => Http::response(['error' => ['message' => 'bad token']], 401),
        ]);

        $this->post('/settings/test/claude')->assertSessionHas('status', 'Claude works. The key is valid.');
        $this->post('/settings/test/apify')->assertSessionHasErrors(['connection' => 'Apify rejected the key. Check APIFY_TOKEN in your .env file.']);
    }

    public function test_account_theme_and_time_zone(): void
    {
        $this->put('/account/theme', ['theme' => 'light'])->assertRedirect();
        $this->assertSame('light', auth()->user()->fresh()->theme);
        $this->get('/')->assertSee('data-theme="light"', false);

        $this->put('/account/preferences', ['theme' => 'system', 'timezone' => 'America/Chicago'])->assertSessionHasNoErrors();
        $this->assertSame('America/Chicago', auth()->user()->fresh()->timezone);

        $this->put('/account/preferences', ['theme' => 'dark', 'timezone' => 'Mars/Base'])->assertSessionHasErrors('timezone');
    }

    /* ---------- Dashboard ---------- */

    public function test_dashboard_greets_and_links_numbers(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Aubre Cyriaque']));
        Business::create(['name' => 'A', 'category' => 'Gyms', 'score' => 80]);

        $this->get('/')
            ->assertSee('Aubre')
            ->assertSee(route('leads', ['score' => 'hot']), false)
            ->assertSee('+1 this week')
            ->assertSee('Waiting on you');
    }

    public function test_waiting_drafts_show_in_the_tab_title(): void
    {
        $lead = Business::create(['name' => 'Glow Spa', 'category' => 'Med spas', 'score' => 70]);
        $this->draftFor($lead);

        $this->get('/leads')->assertSee('<title>(1) Leads · Cloyne</title>', false);
    }
}
