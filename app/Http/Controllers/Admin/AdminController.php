<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdCategory;
use App\Models\AdView;
use App\Models\Earning;
use App\Models\Withdrawal;
use App\Services\EscrowService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $sum = fn ($col, $status = null) => (int) Earning::when($status, fn ($q) => $q->where('status', $status))
            ->where('status', '!=', Earning::REVERSED)->sum($col);

        return view('admin.index', [
            'stats' => [
                'gross' => $sum('gross_micros'),
                'users_share' => $sum('user_micros'),
                'platform' => $sum('platform_micros'),
                'escrow' => $sum('user_micros', Earning::ESCROW),
                'released' => $sum('user_micros', Earning::RELEASED),
                'reversed' => (int) Earning::where('status', Earning::REVERSED)->sum('user_micros'),
                'pending_withdrawals' => (int) Withdrawal::where('status', 'pending')->sum('amount_micros'),
                'paid_out' => (int) Withdrawal::where('status', 'paid')->sum('amount_micros'),
                'views' => AdView::where('status', 'completed')->count(),
                'rejected_views' => AdView::where('status', 'rejected')->count(),
            ],
            'escrow' => Earning::with('user', 'adView.ad')->where('status', Earning::ESCROW)->orderBy('release_at')->limit(25)->get(),
            'withdrawals' => Withdrawal::with('user')->where('status', 'pending')->oldest()->get(),
            'rejected' => AdView::with('user', 'ad')->where('status', 'rejected')->latest()->limit(10)->get(),
            'categories' => AdCategory::withCount('ads')->orderByDesc('cpm_high_cents')->get(),
            // Paginated: the catalogue is ~19k ads, far too many to render (and thumbnail) on one page.
            'ads' => Ad::with('category')
                ->withCount(['views' => fn ($q) => $q->where('status', 'completed')])
                ->when($request->query('ads_q'), fn ($q, $term) => $q->where(fn ($w) => $w
                    ->where('advertiser', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('youtube_id', $term)))
                ->latest('id')
                ->paginate(20, ['*'], 'ads_page')
                ->withQueryString()
                ->fragment('inventory'),
        ]);
    }

    public function releaseDue(EscrowService $escrow)
    {
        return back()->with('status', "Released {$escrow->releaseDue()} earnings whose hold period has ended.");
    }

    public function release(Earning $earning, EscrowService $escrow)
    {
        $escrow->release($earning);

        return back()->with('status', "Earning #{$earning->id} released early.");
    }

    public function reverse(Request $request, Earning $earning, EscrowService $escrow)
    {
        $escrow->reverse($earning, $request->input('reason', 'Invalid traffic'));

        return back()->with('status', "Earning #{$earning->id} reversed.");
    }

    public function processWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        $status = $request->validate(['status' => 'required|in:paid,rejected'])['status'];
        if ($withdrawal->status === 'pending') {
            $withdrawal->update(['status' => $status, 'processed_at' => now()]);
        }

        return back()->with('status', "Withdrawal #{$withdrawal->id} marked {$status}.");
    }

    public function storeAd(Request $request)
    {
        $data = $request->validate([
            'ad_category_id' => 'required|exists:ad_categories,id',
            'advertiser' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'youtube_url' => 'required|string',
            'duration_seconds' => 'required|integer|min:5|max:600',
        ]);

        $id = Ad::parseYoutubeId($data['youtube_url']);
        if (! $id) {
            return back()->withErrors(['youtube_url' => 'Could not find a YouTube video id in that URL.'])->withInput();
        }

        Ad::create([...$data, 'youtube_id' => $id]);

        return back()->with('status', 'Ad added.');
    }

    public function toggleAd(Ad $ad)
    {
        $ad->update(['active' => ! $ad->active]);

        return back();
    }

    public function updateCategory(Request $request, AdCategory $category)
    {
        $data = $request->validate([
            'cpm_low' => 'required|numeric|min:0.01|max:500',
            'cpm_high' => 'required|numeric|gte:cpm_low|max:500',
        ]);
        $category->update([
            'cpm_low_cents' => (int) round($data['cpm_low'] * 100),
            'cpm_high_cents' => (int) round($data['cpm_high'] * 100),
        ]);

        return back()->with('status', "{$category->name} CPM updated.");
    }
}
