@use('App\Support\Money')
<div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-slate-500 text-xs uppercase">
        <tr><th class="p-3">Watched</th><th class="p-3">Ad</th><th class="p-3">Category</th><th class="p-3 text-right">CPM</th>
            <th class="p-3 text-right">Gross</th><th class="p-3 text-right">You (90%)</th><th class="p-3">Status</th></tr>
    </thead>
    <tbody>
    @forelse ($earnings as $e)
        <tr class="border-t border-slate-100">
            <td class="p-3 whitespace-nowrap text-slate-500">{{ $e->created_at->diffForHumans() }}</td>
            <td class="p-3">{{ $e->adView->ad->advertiser }} — {{ $e->adView->ad->title }}</td>
            <td class="p-3 text-slate-500">{{ $e->adView->ad->category->name }}</td>
            <td class="p-3 text-right">${{ number_format($e->cpm_cents / 100, 2) }}</td>
            <td class="p-3 text-right text-slate-500">{{ Money::precise($e->gross_micros) }}</td>
            <td class="p-3 text-right font-semibold">{{ Money::precise($e->user_micros) }}</td>
            <td class="p-3 whitespace-nowrap">
                @if ($e->status === 'escrow')
                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">Escrow · {{ $e->release_at->diffForHumans(null, true) }} left</span>
                @elseif ($e->status === 'released')
                    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-xs">Released</span>
                @else
                    <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs" title="{{ $e->reversal_reason }}">Reversed</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="p-6 text-center text-slate-400">No views yet — go watch an ad.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
