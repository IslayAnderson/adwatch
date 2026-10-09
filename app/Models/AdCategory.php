<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'cpm_low_cents', 'cpm_high_cents', 'source_note'])]
class AdCategory extends Model
{
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /** Midpoint of the published range is what we bill each impression at. */
    public function cpmCents(): int
    {
        return intdiv($this->cpm_low_cents + $this->cpm_high_cents, 2);
    }

    public function cpmRangeLabel(): string
    {
        return sprintf('$%.2f – $%.2f', $this->cpm_low_cents / 100, $this->cpm_high_cents / 100);
    }
}
