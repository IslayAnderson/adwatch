<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\User;
use App\Services\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->withCount(['adViews as views_completed' => fn ($q) => $q->where('status', 'completed')])
            ->withSum(['earnings as escrow_micros' => fn ($q) => $q->where('status', Earning::ESCROW)], 'user_micros')
            ->withSum(['earnings as released_micros' => fn ($q) => $q->where('status', Earning::RELEASED)], 'user_micros')
            ->withSum(['earnings as lifetime_micros' => fn ($q) => $q->where('status', '!=', Earning::REVERSED)], 'user_micros')
            ->withSum(['withdrawals as withdrawn_micros' => fn ($q) => $q->where('status', '!=', 'rejected')], 'amount_micros')
            ->addSelect(['last_active' => DB::table('sessions')->selectRaw('max(last_activity)')->whereColumn('sessions.user_id', 'users.id')])
            ->addSelect(['last_view_at' => DB::table('ad_views')->selectRaw('max(started_at)')->whereColumn('ad_views.user_id', 'users.id')])
            ->when($request->query('q'), fn (Builder $q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('filter') === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when($request->query('filter') === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'totals' => [
                'all' => User::count(),
                'admins' => User::where('is_admin', true)->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
            ],
        ]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user,
            'wallet' => new Wallet($user),
            'views' => $user->adViews()->with('ad.category', 'earning')->latest('id')->limit(25)->get(),
            'viewCounts' => $user->adViews()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'withdrawals' => $user->withdrawals()->latest()->get(),
            'signedInSessions' => DB::table('sessions')->where('user_id', $user->id)->count(),
            'lastActive' => DB::table('sessions')->where('user_id', $user->id)->max('last_activity'),
        ]);
    }

    public function toggleAdmin(Request $request, User $user)
    {
        $this->notYourself($request, $user, 'remove your own admin rights');
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");
    }

    public function toggleSuspended(Request $request, User $user)
    {
        $this->notYourself($request, $user, 'suspend yourself');

        if ($user->isSuspended()) {
            $user->forceFill(['suspended_at' => null])->save();

            return back()->with('status', "{$user->email} has been unsuspended and can log in again.");
        }

        $user->forceFill(['suspended_at' => now()])->save();
        $user->signOutEverywhere();

        return back()->with('status', "{$user->email} has been suspended and signed out.");
    }

    public function password(Request $request, User $user)
    {
        $data = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->changePassword($data['password']);

        // Changing your own password signs you out too; keep the admin's current session alive.
        if ($request->user()->is($user)) {
            auth()->login($user);
            $request->session()->regenerate();
        }

        return back()->with('status', "Password changed for {$user->email}. Their other logins were signed out.");
    }

    public function signOut(Request $request, User $user)
    {
        $this->notYourself($request, $user, 'sign yourself out from here (use Log out)');
        $user->signOutEverywhere();

        return back()->with('status', "{$user->email} has been signed out everywhere.");
    }

    public function destroy(Request $request, User $user)
    {
        $this->notYourself($request, $user, 'delete your own account');
        $request->validate(['confirm_email' => ['required', 'in:'.$user->email]], ['confirm_email.in' => 'Type the user\'s email exactly to confirm.']);

        $email = $user->email;
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            // Views, earnings and withdrawals go with the account (foreign keys cascade).
            $user->withdrawals()->delete();
            $user->earnings()->delete();
            $user->adViews()->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', "Deleted {$email} and all their views, earnings and withdrawals.");
    }

    private function notYourself(Request $request, User $user, string $action): void
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => "You can't {$action}."]);
        }
    }
}
