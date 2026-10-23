<?php

namespace Tests\Feature;

use App\Models\ApiCost;
use App\Models\Search;
use App\Models\User;
use App\Services\Claude;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_claude_call_is_saved_with_its_cost(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'record_audit', 'input' => ['ok' => true]]],
            'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000, 'cache_read_input_tokens' => 1_000_000, 'cache_creation_input_tokens' => 0],
        ])]);

        app(Claude::class)->callTool('claude-haiku-5-5', 'rules', 'brief', ['name' => 'record_audit', 'description' => 'x', 'input_schema' => ['type' => 'object']], purpose: 'audit');

        $cost = ApiCost::sole();
        $this->assertSame('audit', $cost->purpose);
        // 1M input at $0.10 + 1M output at $0.50 + 1M cache reads at $0.01.
        $this->assertEqualsWithDelta(0.61, $cost->amount, 0.0001);
    }

    public function test_crm_reads_monthly_costs_with_the_key(): void
    {
        $this->getJson('/api/costs')->assertUnauthorized();

        ApiCost::create(['service' => 'claude', 'purpose' => 'audit', 'model' => 'claude-haiku-5-5', 'amount' => 1.25]);
        ApiCost::create(['service' => 'claude', 'purpose' => 'draft', 'model' => 'claude-sonnet-5-5', 'amount' => 0.5]);
        Search::create(['category' => 'roofers', 'area' => 'Dallas, TX', 'max_results' => 100, 'found' => 500]);
        config(['services.apify.price_per_1000' => 4]);

        $key = $this->post('/settings/costs-key')->assertRedirect()->getSession()->get('costs_key');
        $this->assertStringStartsWith('cloyne_', $key);
        $this->assertDatabaseMissing('settings', ['value' => json_encode($key)]); // only the hash is kept

        $this->get('/settings')->assertSee('Costs this month')->assertSee('$3.75');

        $this->withToken('wrong')->getJson('/api/costs')->assertUnauthorized();
        $this->withToken($key)->getJson('/api/costs?month=13-2026')->assertStatus(422);
        $this->withToken($key)->getJson('/api/costs')
            ->assertOk()
            ->assertJsonPath('month', now()->format('Y-m'))
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('total', 3.75)
            ->assertJsonPath('items.0.key', 'apify')
            ->assertJsonPath('items.0.amount', 2)
            ->assertJsonPath('items.1.amount', 1.75)
            ->assertJsonPath('items.1.note', '1 audit · 1 email written');
        $this->withToken($key)->getJson('/api/costs?month=2020-01')->assertOk()->assertJsonPath('total', 0);

        $this->delete('/settings/costs-key')->assertRedirect();
        $this->withToken($key)->getJson('/api/costs')->assertUnauthorized();
    }

    public function test_only_the_owner_makes_the_crm_key(): void
    {
        $this->actingAs(User::factory()->role('reviewer')->create());
        $this->post('/settings/costs-key')->assertForbidden();
    }
}
