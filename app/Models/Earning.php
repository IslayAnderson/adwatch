<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ad_view_id', 'cpm_cents', 'gross_micros', 'user_micros', 'platform_micros', 'status', 'release_at', 'released_at', 'reversal_reason'])]
class Earning extends Model
{
    public const ESCROW = 'escrow';
    public const RELEASED = 'released';
    public const REVERSED = 'reversed';

    protected function casts(): array
    {
        return ['release_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adView(): BelongsTo
    {
        return $this->belongsTo(AdView::class);
    }
}
