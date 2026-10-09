<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdCategory;
use App\Models\AdView;
use App\Services\AdViewService;
use App\Services\PayoutCalculator;
use App\Support\CategoryInterleaver;
use App\Support\MarketingNumbers;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    public function index(Request $request, PayoutCalculator $calculator, AdViewService $service)
    {
        $cooldownIds = $service->cooldownAdIds($request->user());

        $region = $request->query('region') === 'GB' ? 'GB' : null;

        $query = Ad::where('active', true)
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('advertiser', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")));

        if ($request->query('category')) {
            $ads = $query->with('category')
                ->orderByDesc(AdCategory::select('cpm_high_cents')->whereColumn('ad_categories.id', 'ads.ad_category_id'))
                ->orderBy('advertiser')
                ->paginate(24)
                ->withQueryString();
        } else {
            // "All": mix categories so no two neighbouring cards share one. The shuffle seed lives in the
            // session so paging through keeps a stable order.
            $seed = $request->session()->remember('ads_shuffle_seed', fn () => random_int(1, PHP_INT_MAX));
            $order = CategoryInterleaver::order($query->pluck('ad_category_id', 'id')->all(), $seed);
            $page = LengthAwarePaginator::resolveCurrentPage();
            $ids = array_slice($order, ($page - 1) * 24, 24);
            $rows = Ad::with('category')->whereIn('id', $ids)->get()->sortBy(fn (Ad $ad) => array_search($ad->id, $ids))->values();
            $ads = (new LengthAwarePaginator($rows, count($order), 24, $page, ['path' => $request->url()]))->withQueryString();
        }

        $ads->through(function (Ad $ad) use ($calculator, $cooldownIds) {
            $ad->payout = $calculator->forAd($ad);
            $ad->on_cooldown = in_array($ad->id, $cooldownIds);

            return $ad;
        });

        return view('ads.index', [
            'ads' => $ads,
            'categories' => AdCategory::whereHas('ads', fn ($q) => $q->when($region, fn ($q) => $q->where('region', $region)))
                ->withCount(['ads' => fn ($q) => $q->when($region, fn ($q) => $q->where('region', $region))])
                ->orderBy('name')->get(),
            'active' => $request->query('category'),
            'region' => $region,
            // Inflated figure for the search placeholder: the headline total, or the category's share.
            'claimedAds' => MarketingNumbers::abbreviate($request->query('category')
                ? MarketingNumbers::byCategory()->get($request->query('category'), 0)
                : MarketingNumbers::total()),
        ]);
    }

    public function start(Request $request, Ad $ad, AdViewService $service)
    {
        $view = $service->start($request->user(), $ad, $request->ip());

        return redirect()->route('watch.show', $view);
    }

    public function watch(Request $request, AdView $adView, PayoutCalculator $calculator)
    {
        abort_unless($adView->user_id === $request->user()->id, 403);
        if ($adView->status !== 'started') {
            return redirect()->route('ads.index')->withErrors(['ad' => 'That view session has ended. Pick an ad to start again.']);
        }

        return view('ads.watch', [
            'view' => $adView->load('ad.category'),
            'payout' => $calculator->forAd($adView->ad),
        ]);
    }

    /** Claim the earning, then chain straight into a random ad from the same category. */
    public function complete(Request $request, AdView $adView, AdViewService $service)
    {
        abort_unless($adView->user_id === $request->user()->id, 403);
        $data = $request->validate(['watched_seconds' => 'required|integer|min:0|max:3600']);

        $earning = $service->complete($adView, $data['watched_seconds']);
        $message = Money::precise($earning->user_micros).' added to escrow — releases '.$earning->release_at->diffForHumans().'.';

        $next = $service->nextInCategory($request->user(), $adView->ad);
        if (! $next) {
            return redirect()->route('ads.index')->with('status', "Nice! {$message} You've watched every {$adView->ad->category->name} ad for now.");
        }

        try {
            $nextView = $service->start($request->user(), $next, $request->ip());
        } catch (ValidationException $e) {
            return redirect()->route('ads.index')->with('status', "Nice! {$message}")->withErrors($e->errors());
        }

        return redirect()->route('watch.show', $nextView)->with('status', "Nice! {$message} Here's another {$adView->ad->category->name} ad.");
    }
}
