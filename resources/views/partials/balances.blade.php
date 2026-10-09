@use('App\Support\Money')
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach ([
        ['Available', $wallet->availableMicros(), 'text-emerald-600', 'Released from escrow, ready to withdraw', true],
        ['In escrow', $wallet->escrowMicros(), 'text-amber-600', 'Held '.config('adwatch.escrow_days').' days per view', false],
        ['Lifetime earned', $wallet->lifetimeMicros(), 'text-slate-800', 'Excludes reversed views', false],
        ['Withdrawn', $wallet->withdrawnMicros(), 'text-slate-500', 'Pending + paid', false],
    ] as [$label, $micros, $color, $hint, $floor])
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
            <div class="text-2xl font-bold {{ $color }} mt-1" title="{{ Money::precise($micros) }}">{{ $floor ? Money::usdFloor($micros) : Money::usd($micros, 3) }}</div>
            <div class="text-xs text-slate-400 mt-1">{{ $hint }}</div>
        </div>
    @endforeach
</div>
