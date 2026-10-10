<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AdWatch' }}</title>
    @include('partials.google-analytics')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<nav class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-2">
        <a href="/" class="font-bold text-emerald-600 whitespace-nowrap">▶ AdWatch</a>
        @auth
            <a href="{{ route('dashboard') }}" class="text-sm {{ request()->routeIs('dashboard') ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}">Dashboard</a>
            <a href="{{ route('ads.index') }}" class="text-sm {{ request()->routeIs('ads.*', 'watch.*') ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}">Watch ads</a>
            <a href="{{ route('watch-party') }}" class="text-sm {{ request()->routeIs('watch-party') ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}">🎉 Watch party</a>
            <a href="{{ route('wallet') }}" class="text-sm {{ request()->routeIs('wallet') ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}">Wallet</a>
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.index') }}" class="text-sm {{ request()->routeIs('admin.*') ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="ml-auto whitespace-nowrap">@csrf
                <span class="text-sm text-slate-500 mr-3">{{ auth()->user()->name }}</span>
                <button class="text-sm text-slate-500 hover:text-slate-800">Log out</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="ml-auto text-sm">Log in</a>
            <a href="{{ route('register') }}" class="text-sm bg-emerald-600 text-white px-3 py-1.5 rounded">Sign up</a>
        @endauth
    </div>
</nav>
{{--<div class="bg-amber-50 border-b border-amber-200 text-amber-800 text-xs text-center py-1">--}}
{{--    This is satire — no ad network is connected and no real money moves. CPMs are published industry estimates.--}}
{{--</div>--}}
@yield('hero')
<main class="max-w-6xl mx-auto px-4 py-8">
    @if (session('status'))
        <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>
@yield('footer')
@stack('scripts')
</body>
</html>
