<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdCategory;
use App\Models\AdView;
use App\Models\Earning;
use App\Models\User;
use App\Services\EscrowService;
use App\Services\Wallet;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdEscrowFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAd(int $lowCents = 1000, int $highCents = 1400): Ad
    {
        $cat = AdCategory::create(['name' => 'Finance', 'slug' => 'finance', 'cpm_low_cents' => $lowCents, 'cpm_high_cents' => $highCents]);

        return Ad::create(['ad_category_id' => $cat->id, 'advertiser' => 'Acme', 'title' => 'Spot', 'youtube_id' => 'VtvjbmoDx-I', 'duration_seconds' => 60]);
    }

    public function test_full_view_pays_90_percent_into_escrow_then_releases(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd(); // midpoint $12 CPM => $0.012 per view

        $this->actingAs($user)->post(route('ads.start', $ad))->assertRedirect();
        $view = AdView::firstOrFail();

        $this->travel(31)->seconds();
        $this->post(route('watch.complete', $view), ['watched_seconds' => 31])->assertRedirect(route('ads.index'));

        $earning = Earning::firstOrFail();
        $this->assertSame(12_000, $earning->gross_micros);
        $this->assertSame(10_800, $earning->user_micros);
        $this->assertSame(1_200, $earning->platform_micros);
        $this->assertSame(Earning::ESCROW, $earning->status);

        $wallet = new Wallet($user);
        $this->assertSame(10_800, $wallet->escrowMicros());
        $this->assertSame(0, $wallet->availableMicros());

        $this->travel(config('adwatch.escrow_days'))->days();
        $this->assertSame(1, app(EscrowService::class)->releaseDue());
        $this->assertSame(10_800, $wallet->availableMicros());
    }

    public function test_view_rejected_when_server_clock_disagrees_with_client(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();
        $this->actingAs($user)->post(route('ads.start', $ad));
        $view = AdView::firstOrFail();

        // Client claims 30s but only 5s have elapsed server-side.
        $this->travel(5)->seconds();
        $this->post(route('watch.complete', $view), ['watched_seconds' => 30])->assertSessionHasErrors('ad');

        $this->assertSame('rejected', $view->fresh()->status);
        $this->assertSame(0, Earning::count());
    }

    public function test_view_cannot_be_claimed_twice_and_ad_has_cooldown(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();
        $this->actingAs($user)->post(route('ads.start', $ad));
        $view = AdView::firstOrFail();
        $this->travel(31)->seconds();

        $this->post(route('watch.complete', $view), ['watched_seconds' => 31]);
        $this->post(route('watch.complete', $view), ['watched_seconds' => 31])->assertSessionHasErrors('ad');
        $this->post(route('ads.start', $ad))->assertSessionHasErrors('ad');

        $this->assertSame(1, Earning::count());
    }

    public function test_reversed_escrow_never_becomes_withdrawable(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();
        $this->actingAs($user)->post(route('ads.start', $ad));
        $this->travel(31)->seconds();
        $this->post(route('watch.complete', AdView::firstOrFail()), ['watched_seconds' => 31]);

        app(EscrowService::class)->reverse(Earning::firstOrFail(), 'Invalid traffic');
        $this->travel(30)->days();
        app(EscrowService::class)->releaseDue();

        $this->assertSame(0, (new Wallet($user))->availableMicros());
    }

    public function test_cannot_withdraw_more_than_released_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('wallet.withdraw'), ['amount' => 5, 'method' => 'paypal', 'destination' => 'me@example.test'])
            ->assertSessionHasErrors('amount');
    }

    public function test_claim_moves_on_to_a_random_unwatched_ad_in_the_same_category(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();
        $sibling = Ad::create(['ad_category_id' => $ad->ad_category_id, 'advertiser' => 'Beta', 'title' => 'Spot 2', 'youtube_id' => 'owGykVbfgUE', 'duration_seconds' => 40]);
        $otherCat = AdCategory::create(['name' => 'Gaming', 'slug' => 'gaming', 'cpm_low_cents' => 300, 'cpm_high_cents' => 600]);
        Ad::create(['ad_category_id' => $otherCat->id, 'advertiser' => 'Gamma', 'title' => 'Spot 3', 'youtube_id' => 'ZUG9qYTJMsI', 'duration_seconds' => 40]);

        $this->actingAs($user)->post(route('ads.start', $ad));
        $this->travel(31)->seconds();
        $response = $this->post(route('watch.complete', AdView::firstOrFail()), ['watched_seconds' => 31]);

        $next = AdView::where('status', 'started')->sole();
        $this->assertSame($sibling->id, $next->ad_id);
        $response->assertRedirect(route('watch.show', $next));

        // Category exhausted -> back to the catalogue.
        $this->travel(31)->seconds();
        $this->post(route('watch.complete', $next), ['watched_seconds' => 31])->assertRedirect(route('ads.index'));
    }

    public function test_available_balance_display_rounds_down_to_whole_cents(): void
    {
        $this->assertSame('$0.00', Money::usdFloor(9_999));
        $this->assertSame('$0.10', Money::usdFloor(107_990));
        $this->assertSame('$1,234.56', Money::usdFloor(1_234_569_999));
    }

    public function test_withdrawal_minimum_is_11_27(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();
        $view = $user->adViews()->create(['ad_id' => $ad->id, 'token' => 't1', 'status' => 'completed', 'required_seconds' => 30, 'started_at' => now()]);
        Earning::create(['user_id' => $user->id, 'ad_view_id' => $view->id, 'cpm_cents' => 1200, 'gross_micros' => 50_000_000,
            'user_micros' => 45_000_000, 'platform_micros' => 5_000_000, 'status' => Earning::RELEASED, 'release_at' => now()]);

        $payload = ['method' => 'paypal', 'destination' => 'me@example.test'];
        $this->actingAs($user)->post(route('wallet.withdraw'), [...$payload, 'amount' => 11.26])->assertSessionHasErrors('amount');
        $this->post(route('wallet.withdraw'), [...$payload, 'amount' => 11.27])->assertSessionHasNoErrors();
        $this->assertSame(11_270_000, (int) $user->withdrawals()->sum('amount_micros'));
    }

    public function test_watch_party_serves_random_ads_and_claims_them_as_json(): void
    {
        $user = User::factory()->create();
        $ad = $this->makeAd();

        $this->actingAs($user)->get(route('watch-party'))->assertOk()->assertSee('Watch party');

        $next = $this->postJson(route('watch-party.next'))->assertOk()
            ->assertJsonPath('ad.youtube_id', $ad->youtube_id)
            ->assertJsonPath('required_seconds', 30)
            ->assertJsonPath('competitor', 'a rival brand'); // only one advertiser in the category

        // Too early: rejected by the server clock, as a JSON 422.
        $this->postJson($next->json('complete_url'), ['watched_seconds' => 30])->assertStatus(422);

        $next = $this->postJson(route('watch-party.next'))->assertOk();
        $this->travel(31)->seconds();
        $this->postJson($next->json('complete_url'), ['watched_seconds' => 31])->assertOk()
            ->assertJsonPath('earned_micros', 10_800)
            ->assertJsonPath('escrow', '$0.011');

        // Only ad is now on cooldown, so the party ends.
        $this->postJson(route('watch-party.next'))->assertStatus(422);
    }

    public function test_homepage_shows_live_catalogue_numbers_and_redirects_members(): void
    {
        $this->makeAd(); // CPM range $10.00 – $14.00

        $page = $this->get('/')->assertOk()
            ->assertSee('Your attention is worth something.')
            ->assertSee('3 easy steps')
            ->assertSee('state of the art brow micro furrowing AI technology to monitor ad attention and enjoyment')
            ->assertSee('3.7M+')
            ->assertDontSee('1 ads')
            ->assertSee('2,000+');

        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_search_bar_quotes_the_inflated_ad_count(): void
    {
        $ad = $this->makeAd();
        $this->actingAs(User::factory()->create());

        $this->get(route('ads.index'))->assertSee('Search 3.7M+ ads by brand or title');

        // Only category -> it gets the whole 3.7M, give or take its fixed ±8% wobble.
        $claimed = \App\Support\MarketingNumbers::byCategory()[$ad->category->slug];
        $this->assertEqualsWithDelta(3_700_000, $claimed, 3_700_000 * 0.08);
        $this->get(route('ads.index', ['category' => $ad->category->slug]))
            ->assertSee('Search '.\App\Support\MarketingNumbers::abbreviate($claimed).' ads');
    }

    public function test_british_filter_on_the_ads_page_and_in_watch_party(): void
    {
        $us = $this->makeAd();
        $uk = Ad::create(['ad_category_id' => $us->ad_category_id, 'advertiser' => 'Greggs', 'title' => 'Sausage roll advert', 'youtube_id' => 'TnzFRV1LwIo', 'duration_seconds' => 30, 'region' => 'GB']);
        $this->actingAs(User::factory()->create());

        $this->get(route('ads.index'))->assertSee('Acme')->assertSee('Greggs');
        $this->get(route('ads.index', ['region' => 'GB']))->assertDontSee('Acme')->assertSee('Greggs')->assertSee('🇬🇧 British ✓');

        foreach (range(1, 5) as $i) {
            $this->postJson(route('watch-party.next'), ['region' => 'GB'])->assertJsonPath('ad.advertiser', 'Greggs');
        }
    }
}
