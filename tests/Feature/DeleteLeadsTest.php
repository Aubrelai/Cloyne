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

    public function test_delete_all_matching_a_filter(): void
    {
        foreach (range(1, 30) as $i) {
            Business::create(['name' => "Dental {$i}", 'category' => 'Dentists']);
        }
        Business::create(['name' => 'Keep Gym', 'category' => 'Gyms']);

        $this->get('/leads?category=Dentists')->assertSee('Delete all 30 matching');
        $this->post('/leads/delete-matching', ['category' => 'Dentists'])->assertRedirect('/leads');

        $this->assertSame(['Keep Gym'], Business::pluck('name')->all());
    }

    public function test_delete_all_matching_refuses_without_a_filter(): void
    {
        Business::create(['name' => 'Keep', 'category' => 'Gyms']);

        $this->post('/leads/delete-matching', [])->assertSessionHasErrors('ids');

        $this->assertSame(1, Business::count());
    }

    public function test_deleting_a_search_removes_it_and_its_leads(): void
    {
        $search = Search::create(['category' => 'Dentists', 'area' => 'Palm Beach', 'max_results' => 100, 'status' => 'done', 'added' => 2]);
        Business::create(['name' => 'D1', 'category' => 'Dentists', 'search_id' => $search->id, 'place_id' => 'p1']);
        Business::create(['name' => 'D2', 'category' => 'Dentists', 'search_id' => $search->id]);
        Business::create(['name' => 'Other', 'category' => 'Gyms']);

        $this->delete("/lead-finder/searches/{$search->id}")->assertRedirect('/lead-finder');

        $this->assertDatabaseMissing('searches', ['id' => $search->id]);
        $this->assertSame(['Other'], Business::pluck('name')->all());
        $this->assertDatabaseHas('dismissed_places', ['place_id' => 'p1']);
    }

    public function test_a_search_disappears_once_all_its_leads_are_deleted(): void
    {
        $search = Search::create(['category' => 'Dentists', 'area' => 'Palm Beach', 'max_results' => 100, 'status' => 'done', 'added' => 1]);
        $lead = Business::create(['name' => 'D1', 'category' => 'Dentists', 'search_id' => $search->id]);

        $this->post('/leads/delete-matching', ['category' => 'Dentists']);

        $this->assertDatabaseMissing('businesses', ['id' => $lead->id]);
        $this->assertDatabaseMissing('searches', ['id' => $search->id]);
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
