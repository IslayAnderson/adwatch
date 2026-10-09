<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\AdView;
use App\Models\Earning;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdViewService
{
    public function __construct(private PayoutCalculator $calculator, private Analytics $analytics) {}

    /** Shared GA4 params describing an ad view. */
    private function adParams(AdView $view): array
    {
        return [
            'ad_id' => $view->ad_id,
            'advertiser' => $view->ad->advertiser,
            'ad_category' => $view->ad->category->name,
            'source' => request()->routeIs('watch-party.*') ? 'watch_party' : 'manual',
        ];
    }

    /** Ids of ads this user already earned from inside the cooldown window. */
    public function cooldownAdIds(User $user): array
    {
        return $user->adViews()->where('status', 'completed')
            ->where('completed_at', '>=', now()->subHours(config('adwatch.same_ad_cooldown_hours')))
            ->pluck('ad_id')->all();
    }

    /** A random watchable ad from the same category, excluding the one just watched. */
    public function nextInCategory(User $user, Ad $after): ?Ad
    {
        return Ad::where('active', true)
            ->where('ad_category_id', $after->ad_category_id)
            ->whereKeyNot($after->id)
            ->whereNotIn('id', $this->cooldownAdIds($user))
            ->inRandomOrder()
            ->first();
    }

    public function start(User $user, Ad $ad, ?string $ip): AdView
    {
        abort_unless($ad->active, 404);

        $todayCount = $user->adViews()->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfDay())->count();
        if ($todayCount >= config('adwatch.daily_view_cap')) {
            throw ValidationException::withMessages(['ad' => 'Daily limit reached. Come back tomorrow.']);
        }

        $recent = $user->adViews()->where('ad_id', $ad->id)->where('status', 'completed')
            ->where('completed_at', '>=', now()->subHours(config('adwatch.same_ad_cooldown_hours')))->exists();
        if ($recent) {
            throw ValidationException::withMessages(['ad' => 'You already earned from this ad recently.']);
        }

        // Only one ad can be "playing" at a time; anything left open is abandoned.
        $user->adViews()->where('status', 'started')->update(['status' => 'abandoned']);

        $view = $user->adViews()->create([
            'ad_id' => $ad->id,
            'token' => (string) Str::uuid(),
            'required_seconds' => $ad->requiredSeconds(),
            'started_at' => now(),
            'ip' => $ip,
        ]);
        $this->analytics->event('ad_view_start', $this->adParams($view) + ['cpm' => $ad->category->cpmCents() / 100]);

        return $view;
    }

    /**
     * The browser reports how long the player actually played. We never trust it alone:
     * the server-side clock since start() must also cover the required watch time.
     */
    public function complete(AdView $view, int $reportedSeconds): Earning
    {
        $result = DB::transaction(function () use ($view, $reportedSeconds) {
            $view = AdView::lockForUpdate()->findOrFail($view->id);

            if ($view->status !== 'started') {
                throw ValidationException::withMessages(['ad' => 'This view is no longer active.']);
            }

            $elapsed = $view->started_at->diffInSeconds(now());
            $grace = 2; // network / player buffering slack

            if ($reportedSeconds < $view->required_seconds || $elapsed + $grace < $view->required_seconds) {
                $view->update([
                    'status' => 'rejected',
                    'reported_seconds' => $reportedSeconds,
                    'reject_reason' => "Watched {$reportedSeconds}s (server saw {$elapsed}s), needed {$view->required_seconds}s",
                ]);

                // Returned rather than thrown so the rejection is committed for fraud review.
                return null;
            }

            $view->update([
                'status' => 'completed',
                'reported_seconds' => $reportedSeconds,
                'completed_at' => now(),
            ]);

            return Earning::create([
                'user_id' => $view->user_id,
                'ad_view_id' => $view->id,
                ...$this->calculator->forAd($view->ad),
                'status' => Earning::ESCROW,
                'release_at' => now()->addDays(config('adwatch.escrow_days')),
            ]);
        });

        if (! $result) {
            $this->analytics->event('ad_view_rejected', $this->adParams($view) + ['watched_seconds' => $reportedSeconds]);
            throw ValidationException::withMessages(['ad' => 'Ad was not watched long enough to qualify.']);
        }

        $this->analytics->event('ad_view_complete', $this->adParams($view) + [
            'watched_seconds' => $reportedSeconds,
            'value' => $result->user_micros / 1_000_000, // the viewer's 90% share
            'currency' => 'USD',
            'gross_value' => $result->gross_micros / 1_000_000,
        ]);
        $this->analytics->purchase($result, $view, $this->adParams($view)['source']);

        return $result;
    }
}
