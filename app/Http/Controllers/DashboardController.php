<?php

namespace App\Http\Controllers;

use App\Services\Wallet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('dashboard', [
            'wallet' => new Wallet($user),
            'todayViews' => $user->adViews()->where('status', 'completed')->where('completed_at', '>=', now()->startOfDay())->count(),
            'recent' => $user->earnings()->with('adView.ad.category')->latest()->limit(10)->get(),
        ]);
    }
}
