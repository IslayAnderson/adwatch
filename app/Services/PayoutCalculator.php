<?php

namespace App\Services;

use App\Models\Ad;

/**
 * Turns an ad's category CPM into the value of a single impression and splits it.
 *
 * CPM = cost per 1,000 impressions, so one view is worth CPM / 1000.
 */
class PayoutCalculator
{
    /** @return array{cpm_cents:int, gross_micros:int, user_micros:int, platform_micros:int} */
    public function forAd(Ad $ad): array
    {
        $cpmCents = $ad->category->cpmCents();

        // cents -> micros is *10,000; then / 1000 impressions => *10
        $gross = $cpmCents * 10;
        $user = (int) floor($gross * config('adwatch.user_share'));

        return [
            'cpm_cents' => $cpmCents,
            'gross_micros' => $gross,
            'user_micros' => $user,
            'platform_micros' => $gross - $user,
        ];
    }
}
