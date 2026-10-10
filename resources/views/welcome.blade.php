@extends('layouts.app', ['title' => 'AdWatch | Get paid to watch ads'])
@use('App\Support\Money')

@section('hero')
{{-- Full-width hero over a wall of real ad thumbnails from the catalogue. --}}
<section class="relative overflow-hidden bg-slate-900">
    <div class="absolute inset-0 grid grid-cols-3 sm:grid-cols-6 gap-1 opacity-40" aria-hidden="true">
        @foreach ($heroAds as $ad)
            <img src="{{ $ad->thumbnailUrl() }}" alt="" class="w-full aspect-video object-cover">
        @endforeach
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-900/80 to-emerald-900/60"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-20 sm:py-28 text-white">
        <p class="uppercase tracking-widest text-emerald-300 text-xs font-semibold mb-3">Free to join · Cash out from ${{ number_format(config('adwatch.min_withdrawal_usd'), 2) }}</p>
        <h1 class="text-4xl sm:text-6xl font-extrabold leading-tight max-w-2xl">Your attention is worth something.</h1>
        <p class="text-lg sm:text-xl text-slate-200 mt-4 max-w-xl">Watch ads from brands you know and keep {{ config('adwatch.user_share') * 100 }}% of what advertisers pay for every view.</p>
        <div class="flex flex-wrap gap-3 mt-8">
            <a href="{{ route('register') }}" class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold px-7 py-3 rounded-full shadow-lg">Start earning</a>
            <a href="{{ route('login') }}" class="border border-white/40 hover:bg-white/10 text-white font-semibold px-7 py-3 rounded-full">I already have an account</a>
        </div>
    </div>
</section>
@endsection

@section('content')
{{-- Intro + "3 easy steps" side card. --}}
<section class="grid lg:grid-cols-5 gap-10 items-start py-6">
    <div class="lg:col-span-3">
        <h2 class="text-3xl font-extrabold text-slate-900">Watch more. Earn more.</h2>
        <p class="text-lg text-slate-600 mt-4">Ads follow you everywhere anyway. Shouldn't you get a cut?</p>
        <p class="text-slate-600 mt-3">Got strong feelings about car commercials? A soft spot for a supermarket jingle? Can't skip a trainer advert without watching it twice? Then you're already doing the hard part.</p>
        <p class="text-slate-800 mt-3"><strong>Earn up to {{ Money::usd($headlinePerView, 4) }} for every ad you watch</strong>, paid into your account the moment it qualifies. You watch, we pay.</p>
        <a href="{{ route('register') }}" class="inline-block mt-5 text-emerald-700 font-semibold hover:underline">Discover how it works ›</a>
    </div>
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-xl font-bold text-slate-900 mb-5">3 easy steps</h3>
        @foreach ([
            ['Register', 'for free', 'Sign up in under a minute. No card, no catch.'],
            ['Watch', 'short ads', 'Pick a category or start a Watch Party and sit back.'],
            ['Cash out', 'your earnings', 'Withdraw to PayPal, your bank or a gift card.'],
        ] as $i => [$verb, $rest, $hint])
            <div class="flex gap-4 {{ $loop->last ? '' : 'mb-5' }}">
                <div class="shrink-0 w-10 h-10 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center">{{ $i + 1 }}</div>
                <div>
                    <p class="text-lg"><span class="font-bold text-emerald-700">{{ $verb }}</span> <span class="font-semibold text-slate-800">{{ $rest }}</span></p>
                    <p class="text-sm text-slate-500">{{ $hint }}</p>
                </div>
            </div>
        @endforeach
        <a href="{{ route('register') }}" class="block text-center mt-6 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-full">Join now — it's free</a>
    </div>
</section>

{{-- Rewards. --}}
<section class="mt-16">
    <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-3xl font-extrabold text-slate-900">Cash out your way</h2>
        <p class="text-slate-600 mt-3">Every ad tops up your balance. Once it passes ${{ number_format(config('adwatch.min_withdrawal_usd'), 2) }}, take it however suits you.</p>
    </div>
    <div class="grid sm:grid-cols-3 gap-5 mt-8">
        @foreach ([
            'paypal' => ['💳', 'Straight to your PayPal balance, ready to spend or move.'],
            'bank' => ['🏦', 'Transferred to your UK or international bank account.'],
            'giftcard' => ['🎁', 'Swap your balance for vouchers from <strong class="font-extrabold text-slate-700">BIG</strong>-name retailers.'],
        ] as $method => [$icon, $text])
            <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center hover:shadow-md transition-shadow">
                <div class="text-4xl">{{ $icon }}</div>
                <h3 class="font-bold text-lg mt-3">{{ config('adwatch.withdrawal_methods')[$method] }}</h3>
                {{-- Hard-coded copy above, so unescaped output is safe here. --}}
                <p class="text-sm text-slate-500 mt-1">{!! $text !!}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Stats band + brand strip. --}}
<section class="mt-16 -mx-4 sm:mx-0 bg-emerald-700 sm:rounded-3xl text-white px-6 py-12">
    <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-3xl font-extrabold">Shape the ads of tomorrow</h2>
        <p class="text-emerald-100 mt-3">Real campaigns from the brands you already buy from. Every view tells them what's working.</p>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-10 text-center">
        @foreach ([
            [$claimedAds, 'ads to watch', config('adwatch.marketing.ads')],
            [config('adwatch.marketing.brands'), 'brands', null],
            [$categories->count(), 'categories', null],
            [config('adwatch.user_share') * 100 .'%', 'of ad revenue to you', null],
        ] as [$value, $label, $fullCount])
            <div>
                {{-- data-count-target: count the full number (e.g. 3,700,000) instead of the abbreviated label. --}}
                <div class="text-4xl font-extrabold tabular-nums [overflow-wrap:anywhere]" data-count-up @if ($fullCount) data-count-target="{{ $fullCount }}" @endif>{{ $value }}</div>
                <div class="text-emerald-100 text-sm mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap justify-center gap-2 mt-10">
        @foreach ($brands as $brand)
            <span class="px-3 py-1 rounded-full bg-white/10 border border-white/20 text-sm">{{ $brand }}</span>
        @endforeach
    </div>
</section>

{{-- Brow Micro-Furrow AI. --}}
<section class="mt-16 rounded-3xl bg-slate-950 text-white overflow-hidden grid lg:grid-cols-2 items-center">
    <div class="p-8 sm:p-12">
        <p class="uppercase tracking-widest text-emerald-400 text-xs font-semibold">Introducing Brow Micro-Furrow&trade; AI</p>
        <h2 class="text-3xl sm:text-4xl font-extrabold mt-3 leading-tight">We know when you're <em class="not-italic text-emerald-400">really</em> watching.</h2>
        <p class="text-slate-300 mt-4 text-lg">AdWatch uses state of the art brow micro furrowing AI technology to monitor ad attention and enjoyment.</p>
        <p class="text-slate-400 mt-3">Our models track 400 eyebrow micro-movements a second across 68 facial landmarks, so advertisers only pay for attention that's real, and you only get paid while you're genuinely enjoying it.</p>
        <ul class="mt-6 space-y-4">
            @foreach ([
                ['🧠', 'Attention scoring', 'Spots a wandering gaze before you do, and pauses the ad until you\'re back.'],
                ['😊', 'Enjoyment verification', 'Frowning? We\'ll wait. Ads restart the moment you smile again.'],
                ['👥', 'Viewer integrity', 'Flags undeclared viewers and competitor products in shot. Fair for everyone.'],
            ] as [$icon, $heading, $text])
                <li class="flex gap-3">
                    <span class="text-2xl leading-none">{{ $icon }}</span>
                    <span><span class="font-semibold">{{ $heading }}.</span> <span class="text-slate-400">{{ $text }}</span></span>
                </li>
            @endforeach
        </ul>
        <p class="text-[11px] text-slate-500 mt-8">Patent pending. Results not typical. Eyebrows sold separately.</p>
    </div>

    {{-- Faux analysis HUD: the same watchful eyes as the webcam overlay, with landmark dots and readouts. --}}
    <div class="relative bg-gradient-to-br from-slate-900 to-emerald-950 h-full min-h-72 flex items-center justify-center p-6">
        <style>
            @keyframes bmf-brow { 0%, 100% { transform: translateY(0) } 40% { transform: translateY(-3px) } 70% { transform: translateY(1.5px) } }
            @keyframes bmf-look { 0%, 100% { transform: translate(0, 0) } 30% { transform: translate(-5px, 0) } 60% { transform: translate(5px, 1px) } }
            @keyframes bmf-scan { 0% { transform: translateY(0) } 100% { transform: translateY(200px) } }
            .bmf-brow { animation: bmf-brow 3.2s ease-in-out infinite; }
            .bmf-pupil { animation: bmf-look 5s ease-in-out infinite; }
            .bmf-scan { animation: bmf-scan 2.4s linear infinite alternate; }
        </style>
        <svg viewBox="0 0 400 260" class="w-full max-w-lg" role="img" aria-label="Diagram of eyes and eyebrows overlaid with tracking points and attention scores">
            <defs>
                <pattern id="bmfGrid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" stroke="#34d399" stroke-opacity=".08"/></pattern>
                @foreach ([130, 270] as $cx)
                    <clipPath id="bmfEye{{ $cx }}"><path d="M{{ $cx - 52 }} 150 Q{{ $cx }} 112 {{ $cx + 52 }} 150 Q{{ $cx }} 180 {{ $cx - 52 }} 150 Z"/></clipPath>
                @endforeach
            </defs>
            <rect width="400" height="260" fill="url(#bmfGrid)"/>
            <rect class="bmf-scan" x="20" y="30" width="360" height="2" fill="#34d399" opacity=".35"/>

            @foreach ([130 => 1, 270 => -1] as $cx => $side)
                <g class="bmf-brow">
                    <path d="M{{ $cx - 44 * $side }} 104 Q{{ $cx }} 92 {{ $cx + 40 * $side }} 110" fill="none" stroke="#e5e7eb" stroke-width="6" stroke-linecap="round"/>
                    @foreach ([-36, -18, 0, 18, 34] as $dx)
                        <circle cx="{{ $cx + $dx * $side }}" cy="{{ 100 + abs($dx + 4) / 9 + ($dx * $side > 0 ? 2 : 0) }}" r="2.6" fill="#34d399"/>
                    @endforeach
                </g>
                <path d="M{{ $cx - 52 }} 150 Q{{ $cx }} 112 {{ $cx + 52 }} 150 Q{{ $cx }} 180 {{ $cx - 52 }} 150 Z" fill="#e5e7eb" fill-opacity=".9"/>
                <g clip-path="url(#bmfEye{{ $cx }})">
                    <g class="bmf-pupil">
                        <circle cx="{{ $cx }}" cy="148" r="18" fill="#4b5563" stroke="#17212b" stroke-width="3"/>
                        <circle cx="{{ $cx }}" cy="148" r="8" fill="#0b1118"/>
                        <circle cx="{{ $cx - 6 }}" cy="142" r="3" fill="#f9fafb"/>
                    </g>
                </g>
                <path d="M{{ $cx - 52 }} 150 Q{{ $cx }} 112 {{ $cx + 52 }} 150" fill="none" stroke="#17212b" stroke-width="4" stroke-linecap="round"/>
                {{-- brow-to-lid measurement --}}
                <line x1="{{ $cx + 22 * $side }}" y1="106" x2="{{ $cx + 22 * $side }}" y2="128" stroke="#34d399" stroke-dasharray="3 3"/>
            @endforeach
            <text x="200" y="122" text-anchor="middle" font-family="ui-monospace, monospace" font-size="10" fill="#6ee7b7">Δ furrow 0.03 mm</text>

            <g font-family="ui-monospace, monospace" font-size="11" font-weight="700">
                <rect x="18" y="24" width="138" height="40" rx="6" fill="#022c22" stroke="#34d399" stroke-opacity=".5"/>
                <text x="28" y="40" fill="#6ee7b7" font-size="9" font-weight="400">ATTENTION</text>
                <text x="28" y="56" fill="#ecfdf5">98.2% <tspan fill="#34d399">▲</tspan></text>
                <rect x="244" y="24" width="138" height="40" rx="6" fill="#022c22" stroke="#34d399" stroke-opacity=".5"/>
                <text x="254" y="40" fill="#6ee7b7" font-size="9" font-weight="400">ENJOYMENT</text>
                <text x="254" y="56" fill="#ecfdf5">87.5% <tspan fill="#fbbf24">●</tspan></text>
                <rect x="110" y="208" width="180" height="30" rx="6" fill="#022c22" stroke="#34d399" stroke-opacity=".5"/>
                <text x="200" y="227" text-anchor="middle" fill="#ecfdf5">BROW TENSION: <tspan fill="#34d399">LOW</tspan></text>
            </g>
        </svg>
    </div>
</section>

{{-- Categories, best-paying first. --}}
<section class="mt-16">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900">More of what you love</h2>
            <p class="text-slate-600 mt-2">Some categories pay more than others. Here's what each view is worth to you.</p>
        </div>
        <a href="{{ route('register') }}" class="text-emerald-700 font-semibold hover:underline">Start watching ›</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        @foreach ($categories as $category)
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold truncate">{{ $category->name }}</div>
                    <div class="text-xs text-slate-500">{{ \App\Support\MarketingNumbers::abbreviate($category->claimed_ads) }} ads</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-emerald-600 font-bold">{{ Money::precise($category->per_view_micros) }}</div>
                    <div class="text-[11px] text-slate-400">per view</div>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Who are we? + Watch Party callout. --}}
<section class="mt-16 grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-8">
        <h2 class="text-2xl font-extrabold text-slate-900">Who are we?</h2>
        <p class="text-slate-600 mt-3">We sit between advertisers and the people they're trying to reach. They pay for real, attentive views. You get the lion's share for giving them. Simple as that.</p>
        <p class="text-slate-600 mt-3">Every view is checked before it pays, and earnings are held for {{ config('adwatch.escrow_days') }} days while the advertiser settles. That keeps things fair for everyone.</p>
    </div>
    <div class="rounded-2xl p-8 text-white bg-gradient-to-br from-emerald-600 to-slate-900">
        <h2 class="text-2xl font-extrabold">🎉 Throw a Watch Party</h2>
        <p class="text-emerald-50 mt-3">Too busy to pick? Hit start and we'll line up ads back to back, banking each one for you as you go.</p>
        <a href="{{ route('register') }}" class="inline-block mt-6 bg-white text-emerald-700 font-bold px-6 py-2.5 rounded-full hover:bg-emerald-50">Join the party</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
// Live-counter effect for the green banner: when it scrolls into view, each number races from 0 to half
// its value, then slows to ticking up by 10 or 20 at a time. Numbers with data-count-target count their
// full value (3.7M+ becomes 1,850,000+ ...), which is far too big to finish, so that one just keeps ticking.
// The server renders the final values, so without JS (or with reduced motion) they show as-is.
(() => {
    const els = document.querySelectorAll('[data-count-up]');
    if (!els.length || !('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const RUSH_MS = 900;  // 0 -> 50%
    const TICK_MS = 35;   // then +10 or +20 per tick

    const parse = (el) => {
        const final = el.textContent.trim();
        if (el.dataset.countTarget) {
            const suffix = final.endsWith('+') ? '+' : '';
            return { final, prefix: '', suffix, target: parseInt(el.dataset.countTarget, 10), endless: true };
        }
        const m = final.match(/^(\D*)([\d,]+)(\D*)$/);
        return m ? { final, prefix: m[1], suffix: m[3], target: parseInt(m[2].replace(/,/g, ''), 10), endless: false } : null;
    };
    const format = (n, p) => p.prefix + n.toLocaleString('en-GB') + p.suffix;

    const tick = (el, p, value) => {
        const timer = setInterval(() => {
            value += Math.random() < 0.5 ? 10 : 20;
            if (!p.endless && value >= p.target) {
                clearInterval(timer);
                el.textContent = p.final;
                return;
            }
            el.textContent = format(value, p);
        }, TICK_MS);
    };

    const run = (el, p) => {
        const half = Math.floor(p.target / 2);
        const start = performance.now();
        const rush = setInterval(() => {
            const t = Math.min((performance.now() - start) / RUSH_MS, 1);
            const eased = 1 - Math.pow(1 - t, 3); // decelerates into the slow phase
            el.textContent = format(Math.floor(half * eased), p);
            if (t === 1) {
                clearInterval(rush);
                tick(el, p, half);
            }
        }, 30);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            run(entry.target, entry.target._count);
        });
    }, { threshold: 0.5 });

    els.forEach((el) => {
        const p = parse(el);
        if (!p) return;
        el._count = p;
        el.textContent = format(0, p);
        observer.observe(el);
    });
})();
</script>
@endpush

@section('footer')
<footer class="mt-16 bg-slate-900 text-slate-400 text-sm">
    <div class="max-w-6xl mx-auto px-4 py-10 grid sm:grid-cols-4 gap-8">
        <div class="sm:col-span-2">
            <div class="font-bold text-emerald-400 text-lg">▶ AdWatch</div>
{{--            <p class="mt-2 max-w-sm">Get paid to watch ads. This is satire: no ad network is connected and no real money moves.</p>--}}
        </div>
        <div>
            <div class="font-semibold text-slate-200 mb-2">Members</div>
            <ul class="space-y-1">
                <li><a href="{{ route('register') }}" class="hover:text-white">Join now</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">Log in</a></li>
            </ul>
        </div>
        <div>
            <div class="font-semibold text-slate-200 mb-2">Company</div>
            <ul class="space-y-1">
                <li><a href="https://blog.islayanderson.co.uk" class="hover:text-white">Blogs</a></li>
                <li><a href="#" class="hover:text-white">Help</a></li>
                <li><a href="#" class="hover:text-white">About us</a></li>
                <li><a href="#" class="hover:text-white">Terms &amp; conditions</a></li>
                <li><a href="#" class="hover:text-white">Privacy</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-slate-800 text-center py-4 text-xs">© {{ date('Y') }} AdWatch | <a href="https://blog.islayanderson.co.uk">Built by Islay Anderson</a> </div>
</footer>
@endsection
