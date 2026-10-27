<?php

namespace Tests\Feature;

use App\Models\ApiCost;
use App\Models\Business;
use App\Models\Call;
use App\Support\Results;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V19Test extends TestCase
{
    use RefreshDatabase;

    private function lead(string $name, string $category, ?int $score): Business
    {
        $b = Business::create(['name' => $name, 'category' => $category]);
        if ($score !== null) {
            $b->recordAudit([['title' => 'x', 'detail' => 'x', 'passed' => false, 'points' => $score]], 'p', 'website');
        }

        return $b;
    }

    private function logCall(Business $b, string $outcome, string $caller): void
    {
        Call::create(['business_id' => $b->id, 'outcome' => $outcome, 'caller' => $caller, 'called_at' => now()]);
    }

    public function test_month_results_count_the_funnel_callers_categories_and_cost(): void
    {
        $roof1 = $this->lead('Roof One', 'Roofers', 80);
        $roof2 = $this->lead('Roof Two', 'Roofers', 65);
        $gym = $this->lead('Gym', 'Gyms', 40);
        $this->lead('New Spa', 'Spas', null);

        $this->logCall($roof1, 'no_answer', 'Jay');
        $this->logCall($roof1, 'booked', 'Jay');
        $roof1->markBooked('call');
        $this->logCall($roof2, 'not_interested', 'Mia');
        $this->logCall($gym, 'voicemail', 'Mia');
        ApiCost::create(['service' => 'claude', 'purpose' => 'audit', 'model' => 'm', 'amount' => 3.5]);

        $r = Results::month(now());

        $this->assertSame([4, 3, 2, 3, 2, 1, 0], array_column($r['funnel'], 'count'));
        $this->assertSame(['caller' => 'Jay', 'calls' => 2, 'talked' => 1, 'booked' => 1, 'rate' => 50], $r['callers']->first());
        $this->assertSame(['caller' => 'Mia', 'calls' => 2, 'talked' => 1, 'booked' => 0, 'rate' => 0], $r['callers']->last());
        $this->assertSame(['category' => 'Roofers', 'leads' => 2, 'hot' => 2, 'called' => 2, 'booked' => 1], $r['categories']->first());
        $this->assertSame(3.5, $r['cost_per_booked']);
    }

    public function test_dashboard_shows_whats_working(): void
    {
        $lead = $this->lead('Roof One', 'Roofers', 80);
        $this->logCall($lead, 'booked', 'Jay');
        $lead->markBooked('call');

        $this->get('/')->assertOk()
            ->assertSee("What's working", false)
            ->assertSeeInOrder(['Found', 'Audited', 'Hot (60+)', 'Called', 'Talked', 'Booked', 'Won'])
            ->assertSee('Jay')
            ->assertSee('100% of talked');
    }

    public function test_empty_month_has_friendly_notes(): void
    {
        $this->get('/')->assertOk()->assertSee('Call results show here once the CRM sends them.')->assertSee('No leads this month yet.');
    }
}
