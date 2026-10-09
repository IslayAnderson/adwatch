<?php

namespace App\Http\Controllers;

use App\Services\Analytics;
use App\Services\Wallet;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('wallet', [
            'wallet' => new Wallet($user),
            'earnings' => $user->earnings()->with('adView.ad.category')->latest()->paginate(20),
            'withdrawals' => $user->withdrawals()->latest()->get(),
        ]);
    }

    public function withdraw(Request $request)
    {
        $min = config('adwatch.min_withdrawal_usd');
        $data = $request->validate([
            'amount' => "required|numeric|min:{$min}",
            'method' => ['required', Rule::in(array_keys(config('adwatch.withdrawal_methods')))],
            'destination' => 'required|string|max:255',
        ]);

        $micros = Money::fromUsd((float) $data['amount']);
        $wallet = new Wallet($request->user());

        if ($micros > $wallet->availableMicros()) {
            throw ValidationException::withMessages(['amount' => 'You can withdraw at most '.Money::usdFloor($wallet->availableMicros()).'.']);
        }

        $request->user()->withdrawals()->create([
            'amount_micros' => $micros,
            'method' => $data['method'],
            'destination' => $data['destination'],
        ]);

        app(Analytics::class)->event('withdrawal_request', ['value' => $micros / 1_000_000, 'currency' => 'USD', 'method' => $data['method']]);

        return back()->with('status', 'Withdrawal of '.Money::usd($micros).' requested. (This is satire — no money moves.)');
    }
}
