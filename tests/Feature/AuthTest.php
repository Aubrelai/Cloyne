<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signInAsOwner = false;

    public function test_guests_are_sent_to_sign_in(): void
    {
        User::factory()->create();

        foreach (['/', '/lead-finder', '/leads', '/settings', '/account'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/leads/delete', ['ids' => [1]])->assertRedirect('/login');
    }

    public function test_first_run_asks_for_the_owner_account(): void
    {
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Create the Owner account');

        $this->post('/setup', [
            'name' => 'Aubre Cyriaque',
            'email' => 'owner@laiq.example',
            'password' => 'long-enough-pass',
            'password_confirmation' => 'long-enough-pass',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertSame('owner', User::sole()->role);
    }

    public function test_setup_closes_once_an_owner_exists(): void
    {
        User::factory()->create();

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', [
            'name' => 'Intruder', 'email' => 'x@example.com',
            'password' => 'long-enough-pass', 'password_confirmation' => 'long-enough-pass',
        ])->assertForbidden();
        $this->assertSame(1, User::count());
    }

    public function test_setup_rejects_short_passwords(): void
    {
        $this->post('/setup', [
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_sign_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'r@example.com', 'password' => 'right-password', 'role' => 'reviewer']);

        $this->post('/login', ['email' => 'r@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'r@example.com', 'password' => 'right-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_too_many_wrong_passwords_locks_sign_in_for_a_while(): void
    {
        User::factory()->create(['email' => 'r@example.com', 'password' => 'right-password']);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'r@example.com', 'password' => 'nope']);
        }

        $this->post('/login', ['email' => 'r@example.com', 'password' => 'right-password'])
            ->assertSessionHasErrors(['email' => 'Too many tries. Wait 15 minutes and try again.']);
        $this->assertGuest();
    }

    public function test_viewers_can_look_but_not_change_anything(): void
    {
        $this->actingAs(User::factory()->role('viewer')->create());
        $lead = Business::create(['name' => 'Keep Dental', 'category' => 'Dentists']);

        foreach (['/', '/lead-finder', '/leads', '/review', '/campaigns', '/inbox', '/booked', '/settings'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/leads')->assertDontSee('Tick leads to delete them')->assertDontSee('>Delete</button>', false);
        $this->get('/lead-finder')->assertDontSee('Start search');

        $this->delete("/leads/{$lead->id}")->assertForbidden();
        $this->post('/leads/delete', ['ids' => [$lead->id]])->assertForbidden();
        $this->post('/leads/audit')->assertForbidden();
        $this->post('/lead-finder/searches', ['category' => 'Gyms', 'area' => 'Dallas, TX', 'max_results' => 100])->assertForbidden();
        $this->post('/settings/team', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'owner'])->assertForbidden();

        $this->assertSame(1, Business::count());
    }

    public function test_reviewers_can_work_leads_but_not_manage_the_team(): void
    {
        $this->actingAs(User::factory()->role('reviewer')->create());
        $lead = Business::create(['name' => 'Gone Dental', 'category' => 'Dentists']);

        $this->get('/settings')->assertOk()->assertSee('Only the Owner can add people');
        $this->delete("/leads/{$lead->id}")->assertRedirect('/leads');
        $this->assertSame(0, Business::count());

        $this->post('/settings/team', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'owner'])->assertForbidden();
        $this->assertSame(1, User::count());
    }

    public function test_owner_adds_a_person_who_must_pick_a_new_password(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/settings/team', ['name' => 'Rae Reviewer', 'email' => 'rae@example.com', 'role' => 'reviewer'])
            ->assertRedirect('/settings')
            ->assertSessionHas('temp_password');

        $temp = session('temp_password')['password'];
        $rae = User::where('email', 'rae@example.com')->sole();
        $this->assertSame('reviewer', $rae->role);
        $this->assertTrue($rae->must_change_password);

        // Rae signs in with the temporary password and is held on the Account page.
        $this->post('/logout');
        $this->post('/login', ['email' => 'rae@example.com', 'password' => $temp])->assertRedirect('/');
        $this->get('/leads')->assertRedirect('/account');

        $this->put('/account/password', [
            'current_password' => $temp,
            'password' => 'raes-own-password',
            'password_confirmation' => 'raes-own-password',
        ])->assertRedirect('/');

        $this->assertFalse($rae->fresh()->must_change_password);
        $this->get('/leads')->assertOk();
    }

    public function test_owner_changes_roles_but_cloyne_keeps_one_owner(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->role('viewer')->create();
        $this->actingAs($owner);

        $this->put("/settings/team/{$viewer->id}", ['role' => 'reviewer']);
        $this->assertSame('reviewer', $viewer->fresh()->role);

        $this->put("/settings/team/{$owner->id}", ['role' => 'viewer'])->assertSessionHasErrors('role', null, 'team');
        $this->assertSame('owner', $owner->fresh()->role);

        $this->delete("/settings/team/{$owner->id}")->assertSessionHasErrors('role', null, 'team');
        $this->assertNotNull($owner->fresh());
    }

    public function test_owner_removes_and_resets_people(): void
    {
        $owner = User::factory()->create();
        $rae = User::factory()->role('reviewer')->create(['password' => 'old-password-123']);
        $sam = User::factory()->role('viewer')->create();
        $this->actingAs($owner);

        $this->post("/settings/team/{$rae->id}/reset-password")->assertSessionHas('temp_password');
        $this->assertTrue($rae->fresh()->must_change_password);
        $this->assertFalse(password_verify('old-password-123', $rae->fresh()->password));

        $this->delete("/settings/team/{$sam->id}");
        $this->assertNull($sam->fresh());
    }

    public function test_create_owner_command(): void
    {
        $this->artisan('cloyne:owner', ['email' => 'boss@laiq.example', '--name' => 'Boss'])->assertSuccessful();

        $boss = User::where('email', 'boss@laiq.example')->sole();
        $this->assertSame('owner', $boss->role);
        $this->assertTrue($boss->must_change_password);
    }
}
