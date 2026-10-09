@extends('layouts.app', ['title' => 'Dashboard'])
@use('App\Support\Money')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Your earnings</h1>
    <a href="{{ route('ads.index') }}" class="bg-emerald-600 text-white px-4 py-2 rounded-lg font-semibold">▶ Watch an ad</a>
</div>
@include('partials.balances')
<div class="grid md:grid-cols-3 gap-4 mt-4 text-sm">
    <div class="bg-white border border-slate-200 rounded-xl p-4">Ads watched today: <strong>{{ $todayViews }} / {{ config('adwatch.daily_view_cap') }}</strong></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">Your share: <strong>{{ config('adwatch.user_share') * 100 }}%</strong> of each impression</div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">Next escrow release:
        <strong>{{ $wallet->nextReleaseAt() ? \Illuminate\Support\Carbon::parse($wallet->nextReleaseAt())->diffForHumans() : '—' }}</strong></div>
</div>
<h2 class="text-lg font-semibold mt-10 mb-3">Recent activity</h2>
@include('partials.earnings-table', ['earnings' => $recent])
@endsection
