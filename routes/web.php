<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WatchPartyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotSuspended;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['auth', EnsureNotSuspended::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/ads', [AdController::class, 'index'])->name('ads.index');
    Route::post('/ads/{ad}/start', [AdController::class, 'start'])->name('ads.start');
    Route::get('/watch/{adView}', [AdController::class, 'watch'])->name('watch.show');
    Route::post('/watch/{adView}/complete', [AdController::class, 'complete'])->name('watch.complete');

    Route::get('/watch-party', [WatchPartyController::class, 'index'])->name('watch-party');
    Route::post('/watch-party/next', [WatchPartyController::class, 'next'])->name('watch-party.next');
    Route::post('/watch-party/alert', [WatchPartyController::class, 'alert'])->name('watch-party.alert');
    Route::post('/watch-party/{adView}/complete', [WatchPartyController::class, 'complete'])->name('watch-party.complete');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');

    Route::middleware(EnsureAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('/escrow/release-due', [AdminController::class, 'releaseDue'])->name('escrow.release-due');
        Route::post('/earnings/{earning}/release', [AdminController::class, 'release'])->name('earnings.release');
        Route::post('/earnings/{earning}/reverse', [AdminController::class, 'reverse'])->name('earnings.reverse');
        Route::post('/withdrawals/{withdrawal}', [AdminController::class, 'processWithdrawal'])->name('withdrawals.process');
        Route::post('/ads', [AdminController::class, 'storeAd'])->name('ads.store');
        Route::post('/ads/{ad}/toggle', [AdminController::class, 'toggleAd'])->name('ads.toggle');
        Route::post('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'toggleSuspended'])->name('users.suspend');
        Route::post('/users/{user}/password', [AdminUserController::class, 'password'])->name('users.password');
        Route::post('/users/{user}/sign-out', [AdminUserController::class, 'signOut'])->name('users.sign-out');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
});
