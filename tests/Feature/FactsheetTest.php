<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_never_mention_prices_or_promise_results(): void
    {
        $answers = collect(config('factsheet.entries'))->pluck('a')->implode(' ');
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d/', $answers);
        $this->assertStringNotContainsStringIgnoringCase('guarantee results', $answers);
        $this->assertContains('Prices, quotes or discounts', config('factsheet.handoff'));
    }

    public function test_owner_edits_adds_removes_and_resets_answers(): void
    {
        $this->get('/settings')->assertSee('Fact sheet for replies')->assertSee('Is there a contract?');

        $this->put('/settings/factsheet', ['entries' => [
            ['q' => 'Is there a contract?', 'a' => 'No. Month to month.'],
            ['q' => '', 'a' => ''],
            ['q' => 'Do you work weekends?', 'a' => 'Our team answers on weekdays.'],
        ]])->assertRedirect()->assertSessionHas('status', 'Fact sheet saved: 2 answers.');

        $this->get('/settings')->assertSee('No. Month to month.')->assertSee('Do you work weekends?')->assertDontSee('Who pays for the ads?');
        $this->assertCount(2, config('factsheet.entries'));

        $this->put('/settings/factsheet', ['entries' => [['q' => 'Half a row', 'a' => '']]])->assertSessionHasErrors('entries', null, 'factsheet');
        $this->put('/settings/factsheet', ['entries' => [['q' => '', 'a' => '']]])->assertSessionHasErrors('entries', null, 'factsheet');

        $this->delete('/settings/factsheet')->assertRedirect();
        $this->get('/settings')->assertSee('Who pays for the ads?');
    }

    public function test_others_can_read_but_not_edit(): void
    {
        $this->actingAs(User::factory()->role('reviewer')->create());
        $this->get('/settings')->assertSee('Who pays for the ads?')->assertSee('Only the Owner can change these.');
        $this->put('/settings/factsheet', ['entries' => [['q' => 'x', 'a' => 'y']]])->assertForbidden();
    }
}
