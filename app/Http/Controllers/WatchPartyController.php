<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdView;
use App\Services\AdViewService;
use App\Services\Analytics;
use App\Services\PayoutCalculator;
use App\Services\Wallet;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * Hands-free mode: the page asks for a random ad, plays it, claims it, repeats.
 * Every view still goes through AdViewService so the same timing checks and escrow apply.
 */
class WatchPartyController extends Controller
{
    public function index(Request $request)
    {
        return view('watch-party', ['wallet' => new Wallet($request->user())]);
    }

    public function next(Request $request, AdViewService $service, PayoutCalculator $calculator)
    {
        $ad = Ad::with('category')->where('active', true)
            ->when($request->input('region') === 'GB', fn ($q) => $q->where('region', 'GB'))
            ->whereNotIn('id', $service->cooldownAdIds($request->user()))
            ->inRandomOrder()
            ->first();

        if (! $ad) {
            return response()->json(['message' => "You've watched every ad available right now. Come back later!"], 422);
        }

        $view = $service->start($request->user(), $ad, $request->ip());
        $payout = $calculator->forAd($ad);

        // A rival brand from the same category, for the fake "competitor product detected" check.
        $competitor = Ad::where('ad_category_id', $ad->ad_category_id)
            ->where('advertiser', '!=', $ad->advertiser)
            ->inRandomOrder()
            ->value('advertiser');

        return response()->json([
            'competitor' => $competitor ?? 'a rival brand',
            'complete_url' => route('watch-party.complete', $view),
            'required_seconds' => $view->required_seconds,
            'payout' => Money::precise($payout['user_micros']),
            'cpm' => '$'.number_format($payout['cpm_cents'] / 100, 2),
            'ad' => [
                'advertiser' => $ad->advertiser,
                'title' => $ad->title,
                'category' => $ad->category->name,
                'youtube_id' => $ad->youtube_id,
            ],
        ]);
    }

    public function complete(Request $request, AdView $adView, AdViewService $service)
    {
        abort_unless($adView->user_id === $request->user()->id, 403);
        $data = $request->validate(['watched_seconds' => 'required|integer|min:0|max:3600']);

        $earning = $service->complete($adView, $data['watched_seconds']);
        $wallet = new Wallet($request->user());

        return response()->json([
            'earned_micros' => $earning->user_micros,
            'earned' => Money::precise($earning->user_micros),
            'escrow' => Money::usd($wallet->escrowMicros(), 3),
            'available' => Money::usdFloor($wallet->availableMicros()),
        ]);
    }

    /** The attention checks run in the browser; it reports them here so they reach GA4 server-side. */
    public function alert(Request $request, Analytics $analytics)
    {
        $data = $request->validate([
            'type' => 'required|in:lookaway,viewer,competitor,shout,smile',
            'action' => 'required|in:shown,cleared',
            'duration_ms' => 'nullable|integer|min:0|max:3600000',
            'trigger' => 'nullable|in:random,forced,keyboard',
        ]);

        $analytics->event('attention_check', [
            'alert_type' => $data['type'],
            'alert_action' => $data['action'],
            'alert_trigger' => $data['trigger'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
        ]);

        return response()->noContent();
    }
}
