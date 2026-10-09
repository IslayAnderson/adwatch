@extends('layouts.app', ['title' => 'Watch ads'])
@use('App\Support\Money')
@section('content')
<h1 class="text-2xl font-bold mb-1">Watch ads</h1>
<p class="text-slate-500 text-sm mb-6">Each view pays the category's estimated CPM ÷ 1,000. You keep {{ config('adwatch.user_share') * 100 }}%.</p>

<form method="GET" action="{{ route('ads.index') }}" class="mb-4 flex gap-2 max-w-md">
    @if ($active)<input type="hidden" name="category" value="{{ $active }}">@endif
    @if ($region)<input type="hidden" name="region" value="{{ $region }}">@endif
    <input name="q" value="{{ request('q') }}" placeholder="Search {{ $claimedAds }} ads by brand or title" class="flex-1 border rounded-lg px-3 py-2 bg-white">
    <button class="bg-slate-800 text-white px-4 rounded-lg text-sm">Search</button>
</form>

<div class="flex flex-wrap gap-2 mb-6">
    {{-- Toggle: British ads only. Keeps the current category and search. --}}
    <a href="{{ route('ads.index', array_filter(['category' => $active, 'q' => request('q'), 'region' => $region ? null : 'GB'])) }}"
       class="px-3 py-1 rounded-full text-sm border font-semibold {{ $region ? 'bg-blue-900 text-white border-blue-900' : 'bg-white border-blue-300 text-blue-900' }}">🇬🇧 British{{ $region ? ' ✓' : '' }}</a>
    <span class="w-px bg-slate-300 mx-1"></span>
    <a href="{{ route('ads.index', array_filter(['region' => $region])) }}" class="px-3 py-1 rounded-full text-sm border {{ ! $active ? 'bg-slate-800 text-white border-slate-800' : 'bg-white border-slate-300' }}">All</a>
    @foreach ($categories as $c)
        <a href="{{ route('ads.index', array_filter(['category' => $c->slug, 'region' => $region])) }}"
           class="px-3 py-1 rounded-full text-sm border {{ $active === $c->slug ? 'bg-slate-800 text-white border-slate-800' : 'bg-white border-slate-300' }}">{{ $c->name }} <span class="opacity-60">{{ $c->ads_count }}</span></a>
    @endforeach
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
@foreach ($ads as $ad)
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden flex flex-col {{ $ad->on_cooldown ? 'opacity-50' : '' }}">
        <img src="{{ $ad->thumbnailUrl() }}" alt="" loading="lazy" class="aspect-video object-cover w-full bg-slate-200">
        <div class="p-4 flex flex-col flex-1">
            <div class="text-xs text-slate-500">{{ $ad->category->name }} · CPM {{ $ad->category->cpmRangeLabel() }}</div>
            <div class="font-semibold mt-1">{{ $ad->advertiser }}@if ($ad->isBritish()) <span title="British advert">🇬🇧</span>@endif</div>
            <div class="text-sm text-slate-600 mb-3 line-clamp-2" title="{{ $ad->title }}">{{ $ad->title }}</div>
            <div class="mt-auto flex items-center justify-between">
                <div>
                    <div class="text-emerald-600 font-bold">+{{ Money::precise($ad->payout['user_micros']) }}</div>
                    <div class="text-xs text-slate-400">watch {{ $ad->requiredSeconds() }}s</div>
                </div>
                @if ($ad->on_cooldown)
                    <span class="text-xs text-slate-500">Watched recently</span>
                @else
                    <form method="POST" action="{{ route('ads.start', $ad) }}">@csrf
                        <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">▶ Watch</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>
@if ($ads->isEmpty())<p class="text-center text-slate-400 py-12">No ads match that search.</p>@endif
<div class="mt-8">{{ $ads->links() }}</div>
@endsection
