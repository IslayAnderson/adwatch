@extends('layouts.app', ['title' => 'Admin · '.$user->name])
@use('App\Support\Money')
@section('content')
@include('admin._nav')

@php $isMe = auth()->user()->is($user); @endphp

<a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-800">‹ All users</a>
<div class="flex flex-wrap items-start justify-between gap-4 mt-2 mb-6">
    <div>
        <h1 class="text-2xl font-bold">
            {{ $user->name }}
            @if ($user->is_admin)<span class="ml-1 align-middle px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-xs font-semibold uppercase">Admin</span>@endif
            @if ($user->isSuspended())<span class="ml-1 align-middle px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs font-semibold uppercase">Suspended</span>@endif
        </h1>
        <p class="text-slate-500">{{ $user->email }}@if ($isMe) <span class="text-xs">(you)</span>@endif</p>
        <p class="text-xs text-slate-400 mt-1">
            Joined {{ $user->created_at->format('j M Y, H:i') }}
            · {{ $signedInSessions ? "Signed in on {$signedInSessions} ".str('device')->plural($signedInSessions) : 'Not signed in' }}
            @if ($lastActive) · last active {{ \Illuminate\Support\Carbon::createFromTimestamp($lastActive)->diffForHumans() }}@endif
            @if ($user->isSuspended()) · suspended {{ $user->suspended_at->diffForHumans() }}@endif
        </p>
    </div>
</div>

@include('partials.balances')

<div class="grid lg:grid-cols-3 gap-6 mt-8">
    <div class="lg:col-span-2 space-y-8">
        <section>
            <h2 class="text-lg font-semibold mb-1">Recent ad views</h2>
            <p class="text-xs text-slate-500 mb-3">
                @forelse ($viewCounts as $status => $n){{ number_format($n) }} {{ $status }}{{ $loop->last ? '' : ' · ' }}@empty No views yet.@endforelse
            </p>
            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">When</th><th class="p-3">Ad</th><th class="p-3">Result</th><th class="p-3 text-right">Earned</th></tr></thead>
                <tbody>
                @forelse ($views as $view)
                    <tr class="border-t border-slate-100">
                        <td class="p-3 whitespace-nowrap text-slate-500">{{ $view->started_at->diffForHumans() }}</td>
                        <td class="p-3">{{ $view->ad->advertiser }}@if ($view->ad->isBritish()) 🇬🇧@endif<div class="text-xs text-slate-400 truncate max-w-xs">{{ $view->ad->title }}</div></td>
                        <td class="p-3 whitespace-nowrap">
                            @php $colours = ['completed' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700', 'started' => 'bg-blue-100 text-blue-700', 'abandoned' => 'bg-slate-100 text-slate-600']; @endphp
                            <span class="px-2 py-0.5 rounded text-xs {{ $colours[$view->status] ?? 'bg-slate-100' }}">{{ ucfirst($view->status) }}</span>
                            @if ($view->reject_reason)<div class="text-xs text-red-600 mt-1">{{ $view->reject_reason }}</div>@endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @if ($view->earning)
                                {{ Money::precise($view->earning->user_micros) }}
                                <div class="text-xs text-slate-400">{{ $view->earning->status }}</div>
                            @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-400">No ad views yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">Withdrawals</h2>
            <div class="bg-white border border-slate-200 rounded-xl text-sm">
            @forelse ($withdrawals as $w)
                <div class="flex flex-wrap items-center gap-3 p-3 border-b border-slate-100 last:border-0">
                    <span class="flex-1">{{ $w->created_at->format('j M Y') }} · {{ config('adwatch.withdrawal_methods')[$w->method] ?? $w->method }} → {{ $w->destination }}</span>
                    <strong>{{ Money::usd($w->amount_micros) }}</strong>
                    @if ($w->status === 'pending')
                        @foreach (['paid' => 'bg-emerald-600', 'rejected' => 'bg-red-600'] as $s => $cls)
                            <form method="POST" action="{{ route('admin.withdrawals.process', $w) }}">@csrf
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button class="{{ $cls }} text-white px-2 py-1 rounded text-xs">Mark {{ $s }}</button>
                            </form>
                        @endforeach
                    @else
                        <span class="text-xs uppercase text-slate-500">{{ $w->status }}</span>
                    @endif
                </div>
            @empty
                <p class="p-4 text-slate-400">No withdrawals.</p>
            @endforelse
            </div>
        </section>
    </div>

    <aside class="space-y-4">
        <h2 class="text-lg font-semibold">Manage</h2>

        <div class="bg-white border border-slate-200 rounded-xl p-4 space-y-3 text-sm">
            <form method="POST" action="{{ route('admin.users.admin', $user) }}" class="flex items-center justify-between gap-3">@csrf
                <span>Admin access</span>
                <button @disabled($isMe) class="px-3 py-1.5 rounded-lg border font-semibold disabled:opacity-40 disabled:cursor-not-allowed {{ $user->is_admin ? 'border-slate-300' : 'border-emerald-600 text-emerald-700' }}">{{ $user->is_admin ? 'Remove admin' : 'Make admin' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="flex items-center justify-between gap-3">@csrf
                <span>{{ $user->isSuspended() ? 'Suspended: can\'t log in' : 'Can log in' }}</span>
                <button @disabled($isMe) class="px-3 py-1.5 rounded-lg font-semibold text-white disabled:opacity-40 disabled:cursor-not-allowed {{ $user->isSuspended() ? 'bg-emerald-600' : 'bg-amber-600' }}">{{ $user->isSuspended() ? 'Unsuspend' : 'Suspend' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.users.sign-out', $user) }}" class="flex items-center justify-between gap-3">@csrf
                <span>{{ $signedInSessions }} active {{ str('login')->plural($signedInSessions) }}</span>
                <button @disabled($isMe) class="px-3 py-1.5 rounded-lg border border-slate-300 font-semibold disabled:opacity-40 disabled:cursor-not-allowed">Sign out everywhere</button>
            </form>
            @if ($isMe)<p class="text-xs text-slate-400">You can't remove your own admin rights, suspend yourself or delete yourself.</p>@endif
        </div>

        <form method="POST" action="{{ route('admin.users.password', $user) }}" class="bg-white border border-slate-200 rounded-xl p-4 space-y-2 text-sm">@csrf
            <div class="font-semibold">Set a new password</div>
            <input type="password" name="password" placeholder="New password (8+ characters)" autocomplete="new-password" class="w-full border rounded px-3 py-1.5" required minlength="8">
            <input type="password" name="password_confirmation" placeholder="Repeat it" autocomplete="new-password" class="w-full border rounded px-3 py-1.5" required>
            <button class="w-full bg-slate-800 text-white rounded py-1.5 font-semibold">Change password</button>
            <p class="text-xs text-slate-400">Signs them out of every device.</p>
        </form>

        @unless ($isMe)
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="bg-white border border-red-200 rounded-xl p-4 space-y-2 text-sm">@csrf @method('DELETE')
            <div class="font-semibold text-red-700">Delete account</div>
            <p class="text-xs text-slate-500">Permanently deletes this user with all their ad views, earnings and withdrawals. This can't be undone. Type <strong>{{ $user->email }}</strong> to confirm.</p>
            <input name="confirm_email" placeholder="{{ $user->email }}" autocomplete="off" class="w-full border rounded px-3 py-1.5">
            <button class="w-full bg-red-600 text-white rounded py-1.5 font-semibold">Delete permanently</button>
        </form>
        @endunless
    </aside>
</div>
@endsection
