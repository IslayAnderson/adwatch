@extends('layouts.app', ['title' => 'Admin · Users'])
@use('App\Support\Money')
@section('content')
@include('admin._nav')

<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <h1 class="text-2xl font-bold">Users <span class="text-base font-normal text-slate-500">({{ number_format($users->total()) }}{{ request('q') || request('filter') ? ' shown' : '' }})</span></h1>
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2 text-sm">
        @if (request('filter'))<input type="hidden" name="filter" value="{{ request('filter') }}">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Search name or email" class="border rounded-lg px-3 py-1.5 bg-white w-56">
        <button class="bg-slate-800 text-white px-3 rounded-lg">Search</button>
    </form>
</div>

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    @foreach (['' => ['All', $totals['all']], 'admins' => ['Admins', $totals['admins']], 'suspended' => ['Suspended', $totals['suspended']]] as $key => [$label, $n])
        <a href="{{ route('admin.users.index', array_filter(['filter' => $key, 'q' => request('q')])) }}"
           class="px-3 py-1 rounded-full border {{ (string) request('filter') === $key ? 'bg-slate-800 text-white border-slate-800' : 'bg-white border-slate-300' }}">{{ $label }} <span class="opacity-60">{{ $n }}</span></a>
    @endforeach
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
        <tr>
            <th class="p-3">User</th><th class="p-3 text-right">Ads watched</th><th class="p-3 text-right">In escrow</th>
            <th class="p-3 text-right">Available</th><th class="p-3 text-right">Lifetime</th><th class="p-3">Last active</th><th class="p-3">Joined</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($users as $u)
        @php
            $available = (int) $u->released_micros - (int) $u->withdrawn_micros;
            $lastActive = collect([$u->last_active ? \Illuminate\Support\Carbon::createFromTimestamp($u->last_active) : null, $u->last_view_at ? \Illuminate\Support\Carbon::parse($u->last_view_at) : null])->filter()->max();
        @endphp
        <tr class="border-t border-slate-100 hover:bg-slate-50 {{ $u->isSuspended() ? 'opacity-60' : '' }}">
            <td class="p-3">
                <a href="{{ route('admin.users.show', $u) }}" class="font-semibold text-emerald-700 hover:underline">{{ $u->name }}</a>
                @if ($u->is_admin)<span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-semibold uppercase">Admin</span>@endif
                @if ($u->isSuspended())<span class="ml-1 px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-semibold uppercase">Suspended</span>@endif
                @if (auth()->user()->is($u))<span class="ml-1 text-xs text-slate-400">(you)</span>@endif
                <div class="text-xs text-slate-500">{{ $u->email }}</div>
            </td>
            <td class="p-3 text-right">{{ number_format($u->views_completed) }}</td>
            <td class="p-3 text-right text-amber-700">{{ Money::usd((int) $u->escrow_micros, 3) }}</td>
            <td class="p-3 text-right text-emerald-700 font-semibold">{{ Money::usdFloor($available) }}</td>
            <td class="p-3 text-right">{{ Money::usd((int) $u->lifetime_micros, 3) }}</td>
            <td class="p-3 whitespace-nowrap text-slate-500">{{ $lastActive?->diffForHumans() ?? '—' }}</td>
            <td class="p-3 whitespace-nowrap text-slate-500">{{ $u->created_at->format('j M Y') }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="p-6 text-center text-slate-400">No users match.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
