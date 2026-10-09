@extends('layouts.app', ['title' => 'Wallet'])
@use('App\Support\Money')
@section('content')
<h1 class="text-2xl font-bold mb-6">Wallet</h1>
@include('partials.balances')

<div class="grid md:grid-cols-3 gap-6 mt-8">
    <form method="POST" action="{{ route('wallet.withdraw') }}" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3 self-start">
        @csrf
        <h2 class="font-semibold">Withdraw</h2>
        <p class="text-xs text-slate-500">Minimum ${{ number_format(config('adwatch.min_withdrawal_usd'), 2) }}. Only released (non-escrow) funds can be withdrawn.</p>
        <input name="amount" type="number" step="0.01" min="{{ config('adwatch.min_withdrawal_usd') }}" placeholder="Amount (USD)"
               value="{{ old('amount') }}" class="w-full border rounded px-3 py-2">
        <select name="method" class="w-full border rounded px-3 py-2">
            @foreach (config('adwatch.withdrawal_methods') as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
        </select>
        <input name="destination" placeholder="PayPal email / account ref" value="{{ old('destination') }}" class="w-full border rounded px-3 py-2">
        <button class="w-full bg-emerald-600 text-white rounded py-2 font-semibold disabled:bg-slate-300"
            @disabled($wallet->availableMicros() < Money::fromUsd(config('adwatch.min_withdrawal_usd')))>Request withdrawal</button>
        @if ($wallet->availableMicros() < Money::fromUsd(config('adwatch.min_withdrawal_usd')))
            @php $perView = 9000; $needed = Money::fromUsd(config('adwatch.min_withdrawal_usd')) - $wallet->availableMicros(); @endphp
            <p class="text-xs text-amber-700">You need {{ Money::usd($needed) }} more released funds (~{{ number_format(ceil($needed / $perView)) }} more ads at a $10 CPM).</p>
        @endif
    </form>

    <div class="md:col-span-2">
        <h2 class="font-semibold mb-3">Withdrawals</h2>
        <div class="bg-white border border-slate-200 rounded-xl">
            @forelse ($withdrawals as $w)
                <div class="flex justify-between p-3 border-b border-slate-100 last:border-0 text-sm">
                    <span>{{ $w->created_at->toDayDateTimeString() }} · {{ config('adwatch.withdrawal_methods')[$w->method] ?? $w->method }}</span>
                    <span class="font-semibold">{{ Money::usd($w->amount_micros) }} <span class="ml-2 text-xs uppercase text-slate-500">{{ $w->status }}</span></span>
                </div>
            @empty
                <p class="p-4 text-sm text-slate-400">No withdrawals yet.</p>
            @endforelse
        </div>
    </div>
</div>

<h2 class="text-lg font-semibold mt-10 mb-3">Earnings ledger</h2>
@include('partials.earnings-table')
<div class="mt-4">{{ $earnings->links() }}</div>
@endsection
