<?php

namespace App\Support;

use App\Models\AdCategory;
use Illuminate\Support\Collection;

/**
 * Deliberately inflated catalogue sizes for marketing copy (homepage, search bar).
 * The headline figure lives in config('adwatch.marketing.ads'); per-category figures share it out
 * in proportion to each category's real size, with a fixed per-category wobble so the split
 * doesn't look computed. Display only; real counts are never changed.
 */
class MarketingNumbers
{
    public static function total(): int
    {
        return config('adwatch.marketing.ads');
    }

    /** @return Collection<string, int> claimed ad count keyed by category slug */
    public static function byCategory(): Collection
    {
        $counts = AdCategory::withCount(['ads' => fn ($q) => $q->where('active', true)])->get();
        $real = max($counts->sum('ads_count'), 1);

        return $counts->mapWithKeys(function (AdCategory $category) use ($real) {
            $wobble = 0.92 + (crc32($category->slug) % 1600) / 10000; // 0.92 – 1.08

            return [$category->slug => (int) (self::total() * $category->ads_count / $real * $wobble)];
        });
    }

    /** 3_700_000 -> "3.7M+", 509_342 -> "509K+". Always rounds down so the "+" stays true-ish. */
    public static function abbreviate(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format(floor($n / 100_000) / 10, 1), '0'), '.').'M+',
            $n >= 1_000 => floor($n / 1_000).'K+',
            default => $n.'+',
        };
    }
}
