<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\User;

class Wallet
{
    public function __construct(private User $user) {}

    public function escrowMicros(): int
    {
        return (int) $this->user->earnings()->where('status', Earning::ESCROW)->sum('user_micros');
    }

    public function releasedMicros(): int
    {
        return (int) $this->user->earnings()->where('status', Earning::RELEASED)->sum('user_micros');
    }

    /** Withdrawals that are pending or paid both reduce what can be withdrawn. */
    public function withdrawnMicros(): int
    {
        return (int) $this->user->withdrawals()->where('status', '!=', 'rejected')->sum('amount_micros');
    }

    public function availableMicros(): int
    {
        return $this->releasedMicros() - $this->withdrawnMicros();
    }

    public function lifetimeMicros(): int
    {
        return (int) $this->user->earnings()->where('status', '!=', Earning::REVERSED)->sum('user_micros');
    }

    public function nextReleaseAt()
    {
        return $this->user->earnings()->where('status', Earning::ESCROW)->min('release_at');
    }
}
