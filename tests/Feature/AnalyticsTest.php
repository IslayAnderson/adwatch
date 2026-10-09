<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdCategory;
use App\Models\AdView;
use App\Models\User;
use App\Services\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'services.google_analytics.measurement_id' => 'G-TEST123',
            'services.google_analytics.api_secret' => 'test-secret',
            'services.google_analytics.debug' => false,
        ]);
        Http::fake(['*google-analytics.com/*' => Http::response('', 204)]);
        // The app registers the real property's session cookie at boot; register the test one too.
        \Illuminate\Cookie\Middleware\EncryptCookies::except(Analytics::sessionCookieName());
        // Browsers send cookies on same-origin fetch(); Laravel's JSON test helpers only do with this.
        $this->withCredentials()->withUnencryptedCookie(Analytics::CONSENT_COOKIE, 'granted');
    }

    /** @return list<array> every event sent to GA, flattened across requests */
    private function sentEvents(): array
    {
        return Http::recorded()->flatMap(fn ($pair) => collect($pair[0]->data()['events'] ?? [])->all())->all();
    }

    private function sentNamed(string $name): ?array
    {
        return collect($this->sentEvents())->firstWhere('name', $name);
    }

    private function makeAd(): Ad
    {
        $cat = AdCategory::create(['name' => 'Finance', 'slug' => 'finance', 'cpm_low_cents' => 1000, 'cpm_high_cents' => 1400]);

        return Ad::create(['ad_category_id' => $cat->id, 'advertiser' => 'Acme', 'title' => 'Spot', 'youtube_id' => 'VtvjbmoDx-I', 'duration_seconds' => 60]);
    }

    public function test_nothing_is_sent_without_an_api_secret(): void
    {
        Http::fake();
        config(['services.google_analytics.measurement_id' => 'G-TEST123', 'services.google_analytics.api_secret' => null]);

        $this->get('/')->assertOk();

        Http::assertNothingSent();
    }

    public function test_page_views_go_to_the_measurement_protocol_with_a_stable_client_id(): void
    {
        $this->enable();

        $first = $this->get('/')->assertOk()->assertCookie(Analytics::CLIENT_COOKIE, null, false);
        $clientId = $first->getCookie(Analytics::CLIENT_COOKIE, false)->getValue();

        Http::assertSent(function (Request $request) use ($clientId) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://www.google-analytics.com/mp/collect?')
                && $query === ['measurement_id' => 'G-TEST123', 'api_secret' => 'test-secret']
                && $request['client_id'] === $clientId
                && $request['events'][0]['name'] === 'page_view'
                && $request['events'][0]['params']['page_title'] === 'AdWatch | Get paid to watch ads'
                && isset($request['events'][0]['params']['session_id'], $request['events'][0]['params']['engagement_time_msec']);
        });

        // Returning visitor keeps the same id; a gtag _ga cookie wins if present.
        $this->withUnencryptedCookie(Analytics::CLIENT_COOKIE, $clientId)->get('/login');
        $this->assertSame($clientId, Http::recorded()->last()[0]['client_id']);

        $this->withUnencryptedCookie('_ga', 'GA1.1.555.777')->get('/register');
        $this->assertSame('555.777', Http::recorded()->last()[0]['client_id']);
    }

    public function test_sign_up_and_login_events(): void
    {
        $this->enable();

        $this->post('/register', ['name' => 'Pat', 'email' => 'pat@example.test', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $this->assertSame(['method' => 'email'], array_intersect_key($this->sentNamed('sign_up')['params'], ['method' => 1]));
        $this->assertNotNull(Http::recorded()->last()[0]['user_id']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'pat@example.test', 'password' => 'secret123']);
        $this->assertNotNull($this->sentNamed('login'));
    }

    public function test_ad_view_events_carry_the_payout(): void
    {
        $this->enable();
        $ad = $this->makeAd();
        $this->actingAs(User::factory()->create());

        $this->post(route('ads.start', $ad));
        $this->assertSame('Acme', $this->sentNamed('ad_view_start')['params']['advertiser']);

        $this->travel(31)->seconds();
        $this->post(route('watch.complete', AdView::firstOrFail()), ['watched_seconds' => 31]);

        $complete = $this->sentNamed('ad_view_complete')['params'];
        $this->assertSame(0.0108, $complete['value']);
        $this->assertSame('USD', $complete['currency']);
        $this->assertSame('manual', $complete['source']);
    }

    public function test_rejected_views_and_watch_party_alerts(): void
    {
        $this->enable();
        $ad = $this->makeAd();
        $this->actingAs(User::factory()->create());

        $next = $this->postJson(route('watch-party.next'))->json();
        $this->assertSame('watch_party', $this->sentNamed('ad_view_start')['params']['source']);

        $this->postJson($next['complete_url'], ['watched_seconds' => 30])->assertStatus(422);
        $this->assertNotNull($this->sentNamed('ad_view_rejected'));

        $this->postJson(route('watch-party.alert'), ['type' => 'shout', 'action' => 'cleared', 'duration_ms' => 1234])->assertNoContent();
        $alert = $this->sentNamed('attention_check')['params'];
        $this->assertSame(['shout', 'cleared', 1234], [$alert['alert_type'], $alert['alert_action'], $alert['duration_ms']]);

        $this->postJson(route('watch-party.alert'), ['type' => 'smile', 'action' => 'shown', 'trigger' => 'keyboard'])->assertNoContent();
        $this->postJson(route('watch-party.alert'), ['type' => 'nope', 'action' => 'shown'])->assertStatus(422);
    }

    public function test_ga_outage_never_breaks_the_page(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123', 'services.google_analytics.api_secret' => 'test-secret']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->get('/')->assertOk();
    }

    public function test_banked_ads_are_ecommerce_purchases_and_reversals_are_refunds(): void
    {
        $this->enable();
        $ad = $this->makeAd(); // $12 CPM midpoint -> $0.012 gross per view
        $this->actingAs(User::factory()->create());

        $this->post(route('ads.start', $ad));
        $this->travel(31)->seconds();
        $this->post(route('watch.complete', AdView::firstOrFail()), ['watched_seconds' => 31]);

        $earning = \App\Models\Earning::firstOrFail();
        $purchase = $this->sentNamed('purchase')['params'];
        $this->assertSame('earning-'.$earning->id, $purchase['transaction_id']);
        $this->assertSame(0.012, $purchase['value']);
        $this->assertSame('USD', $purchase['currency']);
        $this->assertSame(0.0108, $purchase['viewer_payout']);
        $this->assertSame([
            'item_id' => 'ad-'.$ad->id, 'item_name' => 'Spot', 'item_brand' => 'Acme', 'item_category' => 'Finance',
            'item_variant' => 'manual', 'price' => 0.012, 'quantity' => 1,
        ], $purchase['items'][0]);

        // Admin claws it back from escrow -> refund of the same transaction.
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.earnings.reverse', $earning), ['reason' => 'Invalid traffic']);
        $refund = $this->sentNamed('refund')['params'];
        $this->assertSame(['earning-'.$earning->id, 0.012, 'Invalid traffic'], [$refund['transaction_id'], $refund['value'], $refund['refund_reason']]);
    }

    public function test_nothing_is_tracked_until_the_visitor_accepts_cookies(): void
    {
        config([
            'services.google_analytics.measurement_id' => 'G-TEST123',
            'services.google_analytics.api_secret' => 'test-secret',
        ]);
        Http::fake();

        $page = $this->get('/')->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee("analytics_storage: \"denied\"", false)
            ->assertSee('send_page_view: false', false)
            ->assertSee('id="cookieBanner"', false)
            ->assertCookieMissing(Analytics::CLIENT_COOKIE);
        Http::assertNothingSent();

        $this->withUnencryptedCookie(Analytics::CONSENT_COOKIE, 'denied')->get('/')
            ->assertDontSee('id="cookieBanner"', false);
        Http::assertNothingSent();

        $this->withUnencryptedCookie(Analytics::CONSENT_COOKIE, 'granted')->get('/')
            ->assertSee("analytics_storage: \"granted\"", false);
        Http::assertSentCount(1);
    }

    public function test_gtag_session_cookie_is_reused_so_browser_and_server_hits_share_a_session(): void
    {
        $this->enable();

        $this->withUnencryptedCookie('_ga_TEST123', 'GS2.1.s1760000000$o3$g1$t1760000100$j0$l0$h0')->get('/');
        $this->assertSame(1760000000, Http::recorded()->last()[0]['events'][0]['params']['session_id']);

        $this->withUnencryptedCookie('_ga_TEST123', 'GS1.1.1750000000.2.1.1750000100.0.0.0')->get('/');
        $this->assertSame(1750000000, Http::recorded()->last()[0]['events'][0]['params']['session_id']);
    }
}
