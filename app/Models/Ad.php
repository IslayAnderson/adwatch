<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ad_category_id', 'advertiser', 'title', 'youtube_id', 'duration_seconds', 'active', 'region'])]
class Ad extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdCategory::class, 'ad_category_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(AdView::class);
    }

    public function isBritish(): bool
    {
        return $this->region === 'GB';
    }

    public function requiredSeconds(): int
    {
        return min($this->duration_seconds, config('adwatch.min_watch_seconds'));
    }

    public function thumbnailUrl(): string
    {
        return "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg";
    }

    /** Pull the 11-char video id out of any common YouTube URL form. */
    public static function parseYoutubeId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        if (preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        return null;
    }
}
