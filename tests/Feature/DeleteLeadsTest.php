<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Search;
use App\Services\LeadImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteLeadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_one_lead_removes_it_with_its_contacts_and_audits(): void
    {
        $business = Business::create(['name' => 'Gone Dental', 'category' => 'Dentists', 'place_id' => 'p1']);
        $business->contacts()->create(['email' => 'a@gone.example']);
        $business->recordAudit([['title' => 'Website', 'detail' => 'x', 'passed' => true, 'points' => 10]]);

        $this->delete("/leads/{$business->id}")->assertRedirect('/leads');

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
        $this->assertDatabaseMissing('contacts', ['business_id' => $business->id]);
        $this->assertDatabaseMissing('audits', ['business_id' => $business->id]);
        $this->assertDatabaseHas('dismissed_places', ['place_id' => 'p1']);
    }

    public function test_delete_several_selected_leads(): void
    {
        $a = Business::create(['name' => 'A', 'category' => 'Gyms']);
        $b = Business::create(['name' => 'B', 'category' => 'Gyms']);
        $keep = Business::create(['name' => 'Keep', 'category' => 'Gyms']);

        $this->post('/leads/delete', ['ids' => [$a->id, $b->id]])->assertRedirect();

        $this->assertSame(['Keep'], Business::pluck('name')->all());
    }

    public function test_deleting_nothing_shows_an_error(): void
    {
        $this->post('/leads/delete', [])->assertSessionHasErrors('ids');
    }

    public function test_deleted_places_are_skipped_by_later_searches(): void
    {
        $business = Business::create(['name' => 'Gone Dental', 'category' => 'Dentists', 'place_id' => 'p1']);
        $this->delete("/leads/{$business->id}");

        $search = Search::create(['category' => 'Dentists', 'area' => 'Dallas, TX', 'max_results' => 50]);
        $result = app(LeadImporter::class)->import($search, [
            ['title' => 'Gone Dental', 'placeId' => 'p1'],
            ['title' => 'New Dental', 'placeId' => 'p2'],
        ]);

        $this->assertSame(1, $result['added']);
        $this->assertSame(['New Dental'], Business::pluck('name')->all());
    }
}
