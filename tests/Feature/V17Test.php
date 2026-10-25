<?php

namespace Tests\Feature;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Services\Auditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class V17Test extends TestCase
{
    use RefreshDatabase;

    public function test_reputation_is_described_in_words(): void
    {
        $r = fn ($rating, $reviews) => Auditor::reputation(new Business(['rating' => $rating, 'review_count' => $reviews]));

        $this->assertSame('strong: 4.8 stars from 120 reviews', $r(4.8, 120));
        $this->assertSame('good: 4.2 stars from 20 reviews', $r(4.2, 20));
        $this->assertSame('weak: 2.9 stars from 40 reviews', $r(2.9, 40));
        $this->assertSame('modest: 4.6 stars from 3 reviews', $r(4.6, 3));
        $this->assertSame('no Google reviews yet', $r(null, null));
    }

    public function test_audit_weighs_reviews_against_the_website(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $lead = Business::create(['name' => 'Loved Roofing', 'category' => 'Roofers', 'rating' => 4.9, 'review_count' => 210]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'record_audit', 'input' => [
            'checks' => [['title' => 'Reviews vs website', 'detail' => 'Loved on Google, no site', 'found' => false, 'points' => 30]],
            'package' => 'website', 'pitch_package' => null, 'pitch' => '210 happy customers, no website.', 'email' => null, 'disqualified' => false,
        ]]]])]);

        app(Auditor::class)->audit($lead);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'strong: 4.9 stars from 210 reviews')
            && str_contains($request->body(), 'Reviews vs website'));
        $this->assertSame(30, $lead->fresh()->score);
    }

    public function test_old_audits_are_queued_again_oldest_first_within_the_daily_limit(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'test-key']);
        $make = function (string $name, int $daysAgo, string $stage = 'audited') {
            $b = Business::create(['name' => $name, 'category' => 'Roofers']);
            $this->travel(-$daysAgo)->days();
            $b->recordAudit([['title' => 'x', 'detail' => 'x', 'passed' => false, 'points' => 50]], 'p', 'website');
            $this->travelBack();
            $b->update(['stage' => $stage]);

            return $b;
        };
        $oldest = $make('Oldest', 200);
        $old = $make('Old', 90);
        $make('Fresh', 10);
        $make('Booked long ago', 300, 'booked');

        $this->artisan('cloyne:reaudit-stale --limit=1')->expectsOutput('Queued 1 re-audit.')->assertSuccessful();
        Queue::assertPushed(AuditBusiness::class, fn ($job) => $job->business->is($oldest));

        $this->artisan('cloyne:reaudit-stale')->expectsOutput('Queued 1 re-audit.');
        $this->assertSame('queued', $old->fresh()->audit_status);

        $this->artisan('cloyne:reaudit-stale --days=0')->expectsOutput('Re-audits are off.');
    }
}
