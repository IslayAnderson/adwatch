<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('escrow:release', function (App\Services\EscrowService $escrow) {
    $this->info('Released '.$escrow->releaseDue().' earnings from escrow.');
})->purpose('Move escrowed earnings past their hold period into users\' available balance');

Illuminate\Support\Facades\Schedule::command('escrow:release')->hourly();
