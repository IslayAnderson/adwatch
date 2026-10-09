<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Approximate YouTube advertiser CPM ranges (USD) by vertical, as commonly published in
     * creator-economy / ad-benchmark write-ups. Real rates vary by country, season and format.
     */
    private const CATEGORIES = [
        ['Finance & Insurance', 1000, 1500],
        ['Technology & Software', 800, 1200],
        ['Automotive', 700, 1000],
        ['Education', 600, 900],
        ['Health & Wellness', 600, 900],
        ['Travel', 500, 800],
        ['Beauty & Personal Care', 500, 800],
        ['Home & Lifestyle', 500, 800],
        ['Fashion & Sportswear', 400, 700],
        ['Food & Beverage', 400, 600],
        ['Gaming', 300, 600],
        ['Entertainment', 200, 500],
    ];

    /** Real ad videos on YouTube (ids verified via YouTube oEmbed). [category, advertiser, title, id, seconds, region] */
    private const ADS = [
        ['Technology & Software', 'Apple', '1984 Macintosh launch', 'VtvjbmoDx-I', 60, null],
        ['Technology & Software', 'Apple', 'Think Different', '5sMBhDv4sik', 60, null],
        ['Technology & Software', 'Google', 'Parisian Love', 'nnsSUqgkDwU', 52, null],
        ['Automotive', 'Honda', 'The Cog', '_ve4M4UsJQo', 120, 'GB'],
        ['Automotive', 'Volvo Trucks', 'The Epic Split feat. Van Damme', 'M7FIvfx5J10', 76, null],
        ['Beauty & Personal Care', 'Old Spice', 'The Man Your Man Could Smell Like', 'owGykVbfgUE', 33, null],
        ['Beauty & Personal Care', 'Dollar Shave Club', 'Our Blades Are F***ing Great', 'ZUG9qYTJMsI', 94, null],
        ['Home & Lifestyle', 'Blendtec', 'Will It Blend? – iPhone', 'qg1ckCkm8YI', 107, null],
        ['Home & Lifestyle', 'Squatty Potty', 'This Unicorn Changed the Way I Poop', 'YbYWhdLO43Q', 177, null],
        ['Home & Lifestyle', '~Pourri', "Girls Don't Poop", 'ZKLnhuzh9uY', 128, null],
        ['Food & Beverage', 'Cadbury', 'Gorilla', 'TnzFRV1LwIo', 90, 'GB'],
        ['Food & Beverage', 'Coca-Cola', "Hilltop – I'd like to buy the world a Coke", '1VM2eLhvsSM', 60, null],
        ['Fashion & Sportswear', 'Nike', 'Dream Crazy', 'WW2yKSt2C_A', 125, null],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as [$name, $low, $high]) {
            AdCategory::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'cpm_low_cents' => $low,
                'cpm_high_cents' => $high,
                'source_note' => 'Published industry estimate for YouTube in-stream (US)',
            ]);
        }

        $categories = AdCategory::pluck('id', 'name');
        $now = now()->toDateTimeString(); // one string, not ~19k Carbon objects
        $seen = [];
        $batch = [];
        // Tens of thousands of rows: dedupe on the fly and insert in chunks rather than one model at a time.
        foreach ([...self::ADS, ...$this->harvestedAds()] as [$category, $advertiser, $title, $id, $seconds, $region]) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $batch[] = [
                'ad_category_id' => $categories[$category],
                'advertiser' => $advertiser,
                'title' => $title,
                'youtube_id' => $id,
                'duration_seconds' => $seconds,
                'region' => $region,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) === 500) {
                Ad::insert($batch);
                $batch = [];
            }
        }
        if ($batch) {
            Ad::insert($batch);
        }

    }

    /**
     * Ads found via YouTube search ("<advertiser> commercial") for ~120 advertisers across every
     * category, filtered to 10-180s ad-like titles and checked as embeddable via oEmbed.
     */
    private function harvestedAds(): array
    {
        $rows = json_decode(file_get_contents(database_path('data/youtube_ads.json')), true);

        return array_map(fn ($a) => [$a['category'], $a['advertiser'], $a['title'], $a['youtube_id'], $a['duration_seconds'], $a['region'] ?? null], $rows);
    }
}
