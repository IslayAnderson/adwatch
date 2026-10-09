<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdCategory;
use App\Models\AdView;
use App\Models\Earning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Boss', 'email' => 'boss@example.test', 'is_admin' => true]);
    }

    private function viewerWithEarnings(): User
    {
        $user = User::factory()->create(['name' => 'Pat Viewer', 'email' => 'pat@example.test']);
        $cat = AdCategory::create(['name' => 'Food', 'slug' => 'food', 'cpm_low_cents' => 1000, 'cpm_high_cents' => 1400]);
        $ad = Ad::create(['ad_category_id' => $cat->id, 'advertiser' => 'Greggs', 'title' => 'Steak bake', 'youtube_id' => 'abcdefghijk', 'duration_seconds' => 30]);
        foreach ([Earning::ESCROW, Earning::RELEASED] as $i => $status) {
            $view = $user->adViews()->create(['ad_id' => $ad->id, 'token' => "t$i", 'status' => 'completed', 'required_seconds' => 30, 'started_at' => now(), 'completed_at' => now()]);
            Earning::create(['user_id' => $user->id, 'ad_view_id' => $view->id, 'cpm_cents' => 1200, 'gross_micros' => 12000, 'user_micros' => 10800, 'platform_micros' => 1200, 'status' => $status, 'release_at' => now()]);
        }
        $user->adViews()->create(['ad_id' => $ad->id, 'token' => 'rej', 'status' => 'rejected', 'required_seconds' => 30, 'started_at' => now(), 'reject_reason' => 'Watched 5s']);

        return $user;
    }

    public function test_only_admins_can_see_users(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_user_list_shows_balances_and_can_be_searched_and_filtered(): void
    {
        $pat = $this->viewerWithEarnings();
        $this->actingAs($this->admin);

        $this->get(route('admin.users.index'))->assertOk()
            ->assertSee('Pat Viewer')->assertSee('boss@example.test')
            ->assertSee('$0.011')   // escrow 10,800 micros
            ->assertSee('$0.01');   // available, rounded down

        $this->get(route('admin.users.index', ['q' => 'pat@']))->assertSee('Pat Viewer')->assertDontSee('boss@example.test');
        $this->get(route('admin.users.index', ['filter' => 'admins']))->assertSee('Boss')->assertDontSee('Pat Viewer');

        $this->get(route('admin.users.show', $pat))->assertOk()
            ->assertSee('Steak bake')->assertSee('Watched 5s')->assertSee('2 completed');
    }

    public function test_toggle_admin_and_self_protection(): void
    {
        $pat = $this->viewerWithEarnings();
        $this->actingAs($this->admin);

        $this->post(route('admin.users.admin', $pat));
        $this->assertTrue($pat->fresh()->is_admin);
        $this->post(route('admin.users.admin', $pat));
        $this->assertFalse($pat->fresh()->is_admin);

        $this->post(route('admin.users.admin', $this->admin))->assertSessionHasErrors('user');
        $this->post(route('admin.users.suspend', $this->admin))->assertSessionHasErrors('user');
        $this->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => 'boss@example.test'])->assertSessionHasErrors('user');
        $this->assertTrue($this->admin->fresh()->is_admin);
        $this->assertFalse($this->admin->fresh()->isSuspended());
    }

    public function test_suspended_users_are_signed_out_and_cannot_log_in(): void
    {
        $pat = User::factory()->create(['email' => 'pat@example.test', 'password' => 'secret123']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $pat->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->post(route('admin.users.suspend', $pat));
        $this->assertTrue($pat->fresh()->isSuspended());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $pat->id)->count());

        // Already-logged-in requests get bounced...
        $this->actingAs($pat->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        // ...and logging in again is refused.
        $this->post('/login', ['email' => 'pat@example.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->admin)->post(route('admin.users.suspend', $pat));
        $this->assertFalse($pat->fresh()->isSuspended());
        $this->post('/logout');
        $this->post('/login', ['email' => 'pat@example.test', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_set_a_password(): void
    {
        $pat = User::factory()->create();
        $this->actingAs($this->admin);

        $this->post(route('admin.users.password', $pat), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post(route('admin.users.password', $pat), ['password' => 'n3w-password', 'password_confirmation' => 'n3w-password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('n3w-password', $pat->fresh()->password));

        // Changing your own keeps you logged in.
        $this->post(route('admin.users.password', $this->admin), ['password' => 'boss-pass-2', 'password_confirmation' => 'boss-pass-2']);
        $this->assertAuthenticatedAs($this->admin);
        $this->assertTrue(Hash::check('boss-pass-2', $this->admin->fresh()->password));
    }

    public function test_delete_requires_typing_the_email_and_removes_their_data(): void
    {
        $pat = $this->viewerWithEarnings();
        $this->actingAs($this->admin);

        $this->delete(route('admin.users.destroy', $pat), ['confirm_email' => 'wrong@example.test'])->assertSessionHasErrors('confirm_email');
        $this->assertNotNull($pat->fresh());

        $this->delete(route('admin.users.destroy', $pat), ['confirm_email' => 'pat@example.test'])->assertRedirect(route('admin.users.index'));
        $this->assertNull($pat->fresh());
        $this->assertSame(0, AdView::where('user_id', $pat->id)->count());
        $this->assertSame(0, Earning::where('user_id', $pat->id)->count());
        $this->assertSame(1, Ad::count(), 'the ad itself stays');
    }
}
