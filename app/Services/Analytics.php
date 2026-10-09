<?php

namespace App\Services;

use App\Models\AdView;
use App\Models\Earning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server-side Google Analytics 4 via the Measurement Protocol.
 *
 * Events are collected during the request and sent in one batch after the response has gone out
 * (see AppServiceProvider), so a slow or unreachable Google never delays a page. Disabled unless
 * both GA_MEASUREMENT_ID and GA_API_SECRET are set.
 */
class Analytics
{
    /** First-party cookie holding our GA client id when there's no gtag `_ga` cookie to reuse. */
    public const CLIENT_COOKIE = 'aw_ga_cid';

    /** Cookie-banner choice: "granted" or "denied". */
    public const CONSENT_COOKIE = 'aw_consent';

    private const SESSION_TIMEOUT = 30 * 60;

    /** @var list<array{name: string, params: array}> */
    private array $pending = [];

    private ?Request $request = null;

    public function enabled(): bool
    {
        return filled(config('services.google_analytics.measurement_id'))
            && filled(config('services.google_analytics.api_secret'));
    }

    /** Has this visitor opted in to analytics (or is consent not required)? */
    public function consented(?Request $request = null): bool
    {
        return ! config('services.google_analytics.require_consent')
            || ($request ?? request())->cookie(self::CONSENT_COOKIE) === 'granted';
    }

    /** Name of gtag's per-property session cookie, e.g. "_ga_DDY4W6BCG0". */
    public static function sessionCookieName(?string $measurementId = null): string
    {
        return '_ga_'.str_replace('G-', '', (string) ($measurementId ?? config('services.google_analytics.measurement_id')));
    }

    /** Queue an event for this request. Names: max 40 chars; params: max 25 per event. */
    public function event(string $name, array $params = []): void
    {
        if (! $this->enabled() || ! $this->consented() || app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $this->request ??= request();
        $this->pending[] = [
            'name' => Str::limit($name, 40, ''),
            'params' => array_slice(array_map(
                fn ($v) => is_string($v) ? Str::limit($v, 100, '') : $v,
                array_filter($params, fn ($v) => $v !== null)
            ), 0, 20, true),
        ];
    }

    /**
     * GA4 ecommerce: a banked ad view is recorded as a sale. The "product" is the ad impression and the
     * revenue is what the advertiser pays for it (gross); the viewer's share rides along as viewer_payout.
     * transaction_id is the earning id, so GA de-duplicates retries and refunds can target it.
     */
    public function purchase(Earning $earning, AdView $view, string $source): void
    {
        $gross = $earning->gross_micros / 1_000_000;

        $this->event('purchase', [
            'transaction_id' => 'earning-'.$earning->id,
            'value' => $gross,
            'currency' => 'USD',
            'affiliation' => 'AdWatch',
            'viewer_payout' => $earning->user_micros / 1_000_000,
            'platform_revenue' => $earning->platform_micros / 1_000_000,
            'items' => [[
                'item_id' => 'ad-'.$view->ad_id,
                'item_name' => Str::limit($view->ad->title, 100, ''),
                'item_brand' => $view->ad->advertiser,
                'item_category' => $view->ad->category->name,
                'item_variant' => $source,
                'price' => $gross,
                'quantity' => 1,
            ]],
        ]);
    }

    /** An escrowed earning was clawed back: refund the matching purchase in full. */
    public function refund(Earning $earning, string $reason): void
    {
        $this->event('refund', [
            'transaction_id' => 'earning-'.$earning->id,
            'value' => $earning->gross_micros / 1_000_000,
            'currency' => 'USD',
            'refund_reason' => $reason,
        ]);
    }

    /** Send everything queued for this request. Called after the response is sent. */
    public function flush(): void
    {
        if (! $this->pending || ! $this->enabled() || ! $this->request) {
            return;
        }

        $request = $this->request;
        $events = $this->pending;
        $this->pending = [];
        $this->request = null;

        $common = [
            'session_id' => $this->sessionId($request),
            'engagement_time_msec' => 100,
        ];
        if (config('services.google_analytics.debug')) {
            $common['debug_mode'] = true;
        }

        $payload = array_filter([
            'client_id' => $this->clientId($request),
            // Analytics only: never used for ads.
            'consent' => ['ad_user_data' => 'DENIED', 'ad_personalization' => 'DENIED'],
            'user_id' => $request->user()?->getAuthIdentifier() ? (string) $request->user()->getAuthIdentifier() : null,
            'ip_override' => $request->ip(),
            'device' => array_filter([
                'category' => $this->deviceCategory($request->userAgent() ?? ''),
                'language' => strtolower(substr((string) $request->getPreferredLanguage(), 0, 10)) ?: null,
            ]),
            'events' => array_map(fn ($e) => ['name' => $e['name'], 'params' => $e['params'] + $common], $events),
        ]);

        foreach (array_chunk($payload['events'], 25) as $chunk) {
            $this->send(['events' => $chunk] + $payload);
        }
    }

    private function send(array $payload): void
    {
        $config = config('services.google_analytics');
        $query = http_build_query(['measurement_id' => $config['measurement_id'], 'api_secret' => $config['api_secret']]);

        try {
            if ($config['debug']) {
                $result = Http::timeout(3)->post("{$config['endpoint']}/debug/mp/collect?{$query}", $payload)->json();
                Log::debug('GA4 validation', ['events' => array_column($payload['events'], 'name'), 'messages' => $result['validationMessages'] ?? $result]);
            }
            Http::timeout(3)->post("{$config['endpoint']}/mp/collect?{$query}", $payload);
        } catch (Throwable $e) {
            Log::warning('GA4 send failed: '.$e->getMessage());
        }
    }

    /**
     * Reuse the gtag client id if a `_ga` cookie exists ("GA1.1.123.456" -> "123.456"), otherwise our
     * own first-party id (set by the TrackPageView middleware).
     */
    public function clientId(Request $request): string
    {
        if (preg_match('/^GA\d\.\d\.(\d+\.\d+)$/', (string) $request->cookie('_ga'), $m)) {
            return $m[1];
        }

        return $request->cookie(self::CLIENT_COOKIE) ?: self::newClientId();
    }

    public static function newClientId(): string
    {
        return random_int(1_000_000_000, 2_147_483_647).'.'.time();
    }

    /**
     * GA sessions. Prefer gtag's own session id (from its _ga_<id> cookie) so browser and server hits
     * land in the same session; otherwise keep our own, renewed after 30 minutes of inactivity.
     */
    private function sessionId(Request $request): int
    {
        // "GS1.1.1700000000.3.1.…" (older) or "GS2.1.s1700000000$o3$g1…" (newer)
        if (preg_match('/^GS\d\.\d\.s?(\d+)/', (string) $request->cookie(self::sessionCookieName()), $m)) {
            return (int) $m[1];
        }
        if (! $request->hasSession()) {
            return time();
        }
        $session = $request->session();
        if (! $session->has('ga_session_id') || time() - $session->get('ga_last_hit', 0) > self::SESSION_TIMEOUT) {
            $session->put('ga_session_id', time());
        }
        $session->put('ga_last_hit', time());
        $session->save(); // flush runs after the response, so persist explicitly

        return $session->get('ga_session_id');
    }

    private function deviceCategory(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|Nexus (7|9|10)|SM-T/i', $userAgent) => 'tablet',
            (bool) preg_match('/Mobi|Android|iPhone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
