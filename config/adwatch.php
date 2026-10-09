<?php

return [
    // Share of the calculated impression revenue paid to the viewer.
    'user_share' => (float) env('ADWATCH_USER_SHARE', 0.90),

    // How long earnings sit in escrow before they become withdrawable.
    // Mirrors the delay between serving an impression and the ad network settling it.
    'escrow_days' => (int) env('ADWATCH_ESCROW_DAYS', 7),

    // YouTube counts a skippable in-stream view after 30s (or the full ad if shorter).
    'min_watch_seconds' => 30,

    // Basic abuse limits.
    'daily_view_cap' => 50, // default; admins can change it at /admin (stored in the settings table, 0 = unlimited)
    'same_ad_cooldown_hours' => 24,

    // Minimum withdrawal, in USD.
    'min_withdrawal_usd' => 11.27,

    // Homepage headline stats. Marketing copy, deliberately not the real catalogue size.
    'marketing' => [
        'ads' => 3_700_000,   // shown as "3.7M+" and shared out across the category cards
        'brands' => '2,000+',
    ],

    'withdrawal_methods' => ['paypal' => 'PayPal', 'bank' => 'Bank transfer', 'giftcard' => 'Gift card'],
];
