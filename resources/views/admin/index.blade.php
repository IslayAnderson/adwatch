@extends('layouts.app', ['title' => 'Admin'])
@use('App\Support\Money')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Platform admin</h1>
    <form method="POST" action="{{ route('admin.escrow.release-due') }}">@csrf
        <button class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm">Run escrow release (due)</button>
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    @foreach ([
        ['Gross ad revenue', $stats['gross']], ['Owed to users (90%)', $stats['users_share']], ['Platform revenue (10%)', $stats['platform']],
        ['Held in escrow', $stats['escrow']], ['Released to users', $stats['released']],
        ['Reversed (clawed back)', $stats['reversed']], ['Withdrawals pending', $stats['pending_withdrawals']], ['Paid out', $stats['paid_out']],
    ] as [$label, $micros])
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="text-xl font-bold mt-1">{{ Money::usd($micros, 3) }}</div>
        </div>
    @endforeach
    <div class="bg-white border border-slate-200 rounded-xl p-4"><div class="text-xs text-slate-500">Qualified views</div><div class="text-xl font-bold mt-1">{{ number_format($stats['views']) }}</div></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><div class="text-xs text-slate-500">Rejected views</div><div class="text-xl font-bold mt-1">{{ number_format($stats['rejected_views']) }}</div></div>
</div>

<h2 class="text-lg font-semibold mt-10 mb-3">Pending withdrawals</h2>
<div class="bg-white border border-slate-200 rounded-xl">
@forelse ($withdrawals as $w)
    <div class="flex items-center gap-4 p-3 border-b border-slate-100 last:border-0 text-sm">
        <span class="flex-1">{{ $w->user->email }} · {{ $w->method }} → {{ $w->destination }}</span>
        <strong>{{ Money::usd($w->amount_micros) }}</strong>
        @foreach (['paid' => 'bg-emerald-600', 'rejected' => 'bg-red-600'] as $s => $cls)
            <form method="POST" action="{{ route('admin.withdrawals.process', $w) }}">@csrf
                <input type="hidden" name="status" value="{{ $s }}">
                <button class="{{ $cls }} text-white px-3 py-1 rounded text-xs">Mark {{ $s }}</button>
            </form>
        @endforeach
    </div>
@empty
    <p class="p-4 text-sm text-slate-400">Nothing pending.</p>
@endforelse
</div>

<h2 class="text-lg font-semibold mt-10 mb-3">Escrow queue <span class="text-sm font-normal text-slate-500">(next 25 to release)</span></h2>
<div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">User</th><th class="p-3">Ad</th><th class="p-3 text-right">Gross</th><th class="p-3 text-right">User</th><th class="p-3 text-right">Platform</th><th class="p-3">Releases</th><th class="p-3"></th></tr></thead>
    <tbody>
    @forelse ($escrow as $e)
        <tr class="border-t border-slate-100">
            <td class="p-3">{{ $e->user->email }}</td>
            <td class="p-3">{{ $e->adView->ad->advertiser }}</td>
            <td class="p-3 text-right">{{ Money::precise($e->gross_micros) }}</td>
            <td class="p-3 text-right">{{ Money::precise($e->user_micros) }}</td>
            <td class="p-3 text-right">{{ Money::precise($e->platform_micros) }}</td>
            <td class="p-3 whitespace-nowrap">{{ $e->release_at->diffForHumans() }}</td>
            <td class="p-3 flex gap-2 justify-end">
                <form method="POST" action="{{ route('admin.earnings.release', $e) }}">@csrf<button class="text-xs bg-emerald-600 text-white px-2 py-1 rounded">Release now</button></form>
                <form method="POST" action="{{ route('admin.earnings.reverse', $e) }}">@csrf
                    <input type="hidden" name="reason" value="Invalid traffic (admin)">
                    <button class="text-xs bg-red-600 text-white px-2 py-1 rounded">Reverse</button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="p-4 text-center text-slate-400">Escrow is empty.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@if ($rejected->isNotEmpty())
<h2 class="text-lg font-semibold mt-10 mb-3">Recently rejected views</h2>
<div class="bg-white border border-slate-200 rounded-xl text-sm">
    @foreach ($rejected as $r)
        <div class="p-3 border-b border-slate-100 last:border-0">{{ $r->user->email }} · {{ $r->ad->advertiser }} — <span class="text-red-600">{{ $r->reject_reason }}</span></div>
    @endforeach
</div>
@endif

<div class="grid md:grid-cols-2 gap-6 mt-10">
    <div>
        <h2 class="text-lg font-semibold mb-3">Category CPMs</h2>
        <div class="bg-white border border-slate-200 rounded-xl text-sm">
        @foreach ($categories as $c)
            <form method="POST" action="{{ route('admin.categories.update', $c) }}" class="flex items-center gap-2 p-3 border-b border-slate-100 last:border-0">@csrf
                <span class="flex-1">{{ $c->name }} <span class="text-slate-400">({{ $c->ads_count }} ads)</span></span>
                $<input name="cpm_low" type="number" step="0.01" value="{{ $c->cpm_low_cents / 100 }}" class="w-20 border rounded px-2 py-1">
                –
                $<input name="cpm_high" type="number" step="0.01" value="{{ $c->cpm_high_cents / 100 }}" class="w-20 border rounded px-2 py-1">
                <button class="text-xs bg-slate-800 text-white px-2 py-1 rounded">Save</button>
            </form>
        @endforeach
        </div>
        <p class="text-xs text-slate-400 mt-2">Each view is billed at the midpoint of the range. Ranges are published YouTube CPM estimates by vertical.</p>
    </div>
    <div>
        <h2 id="inventory" class="text-lg font-semibold mb-3 scroll-mt-4">Ad inventory <span class="text-sm font-normal text-slate-500">({{ number_format($ads->total()) }}{{ request('ads_q') ? ' matching' : '' }})</span></h2>
        <form method="POST" action="{{ route('admin.ads.store') }}" class="bg-white border border-slate-200 rounded-xl p-4 grid grid-cols-2 gap-2 text-sm mb-4">@csrf
            <input name="advertiser" placeholder="Advertiser" value="{{ old('advertiser') }}" class="border rounded px-2 py-1">
            <input name="title" placeholder="Ad title" value="{{ old('title') }}" class="border rounded px-2 py-1">
            <input name="youtube_url" placeholder="YouTube URL or id" value="{{ old('youtube_url') }}" class="border rounded px-2 py-1 col-span-2">
            <select name="ad_category_id" class="border rounded px-2 py-1">
                @foreach ($categories->sortBy('name') as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            <input name="duration_seconds" type="number" placeholder="Length (s)" value="{{ old('duration_seconds') }}" class="border rounded px-2 py-1">
            <button class="col-span-2 bg-emerald-600 text-white rounded py-1.5 font-semibold">Add ad</button>
        </form>
        <form method="GET" action="{{ route('admin.index') }}#inventory" class="flex gap-2 mb-2 text-sm">
            <input name="ads_q" value="{{ request('ads_q') }}" placeholder="Search advertiser, title or YouTube id" class="flex-1 border rounded px-2 py-1 bg-white">
            <button class="bg-slate-800 text-white px-3 rounded">Search</button>
            @if (request('ads_q'))<a href="{{ route('admin.index') }}#inventory" class="self-center text-slate-500">Clear</a>@endif
        </form>
        <div class="bg-white border border-slate-200 rounded-xl text-sm">
        @foreach ($ads as $ad)
            <div class="flex items-center gap-2 p-2 border-b border-slate-100 last:border-0 {{ $ad->active ? '' : 'opacity-50' }}">
                <img src="{{ $ad->thumbnailUrl() }}" class="w-16 rounded" alt="" loading="lazy">
                <span class="flex-1">{{ $ad->advertiser }} — {{ $ad->title }}<br><span class="text-xs text-slate-400">{{ $ad->category->name }} · {{ $ad->views_count }} views</span></span>
                <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}">@csrf<button class="text-xs border px-2 py-1 rounded">{{ $ad->active ? 'Disable' : 'Enable' }}</button></form>
            </div>
        @endforeach
        @if ($ads->isEmpty())<p class="p-4 text-slate-400">No ads match.</p>@endif
        </div>
        <div class="mt-3">{{ $ads->links() }}</div>
    </div>
</div>
@endsection
