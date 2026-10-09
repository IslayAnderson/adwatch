<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable settings stored in the `settings` table, falling back to config/adwatch.php.
 * Values are cached and the cache is cleared whenever one is changed.
 */
class Settings
{
    private const CACHE_KEY = 'adwatch.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? json_decode($all[$key], true) : ($default ?? config("adwatch.{$key}"));
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::CACHE_KEY);
    }

    /** Ads a user can complete per day; 0 means unlimited. */
    public static function dailyViewCap(): int
    {
        return (int) self::get('daily_view_cap');
    }
}
