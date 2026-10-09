<?php

namespace App\Providers;

use App\Services\Analytics;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Analytics::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // GA cookies are read as-is: gtag's and the consent banner's are set by JS, so they can't be encrypted.
        EncryptCookies::except(['_ga', Analytics::sessionCookieName(), Analytics::CLIENT_COOKIE, Analytics::CONSENT_COOKIE]);

        // Send queued GA4 events once the response has been delivered.
        $this->app->terminating(fn () => $this->app->make(Analytics::class)->flush());
    }
}
