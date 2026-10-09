<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdCategory;
use App\Services\PayoutCalculator;
use App\Support\MarketingNumbers;

class HomeController extends Controller
{
    public function __invoke(PayoutCalculator $calculator)
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $active = fn ($q) => $q->where('active', true);
        $categories = AdCategory::whereHas('ads', $active)->withCount(['ads' => $active])->get()
            ->map(function (AdCategory $category) {
                $category->per_view_micros = (int) floor($category->cpmCents() * 10 * config('adwatch.user_share'));

                return $category;
            })
            ->sortByDesc('per_view_micros')
            ->values();

        $claimed = MarketingNumbers::byCategory();
        $categories->each(fn (AdCategory $category) => $category->claimed_ads = $claimed[$category->slug]);

        return view('welcome', [
            'claimedAds' => MarketingNumbers::abbreviate(MarketingNumbers::total()),
            'categories' => $categories,
            'headlinePerView' => $this->randomPerView(),
            // A wall of real ad thumbnails for the hero background and the brand strip.
            'heroAds' => Ad::where('active', true)->inRandomOrder()->limit(18)->get(),
            'brands' => Ad::where('active', true)->select('advertiser')->distinct()->inRandomOrder()->limit(24)->pluck('advertiser'),
        ]);
    }

    /**
     * Marketing figure for "Earn up to $X": a random per-ad payout, re-rolled each page load, anywhere
     * between the viewer's share at the lowest and highest CPM on record ($2.00 -> $0.0018 up to
     * $15.00 -> $0.0135). Rounded down to 4 decimal places. Display only; nothing is stored.
     */
    private function randomPerView(): int
    {
        $perView = fn (int $cents) => (int) floor($cents * 10 * config('adwatch.user_share'));
        $low = $perView((int) AdCategory::min('cpm_low_cents'));
        $high = $perView((int) AdCategory::max('cpm_high_cents'));

        return intdiv(random_int($low, max($low, $high)), 100) * 100;
    }
}
