<?php

namespace App\Services;

use App\Models\Earning;

class EscrowService
{
    public function __construct(private Analytics $analytics) {}

    /** Release every escrowed earning whose hold period has passed. Returns count released. */
    public function releaseDue(): int
    {
        return Earning::where('status', Earning::ESCROW)
            ->where('release_at', '<=', now())
            ->update(['status' => Earning::RELEASED, 'released_at' => now()]);
    }

    public function release(Earning $earning): void
    {
        if ($earning->status === Earning::ESCROW) {
            $earning->update(['status' => Earning::RELEASED, 'released_at' => now()]);
        }
    }

    /** Claw back an escrowed earning, e.g. the advertiser flagged the impression as invalid traffic. */
    public function reverse(Earning $earning, string $reason): void
    {
        if ($earning->status === Earning::ESCROW) {
            $earning->update(['status' => Earning::REVERSED, 'reversal_reason' => $reason]);
            $earning->adView()->update(['status' => 'rejected', 'reject_reason' => $reason]);
            $this->analytics->refund($earning, $reason);
        }
    }
}
