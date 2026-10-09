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

Artisan::command('user:password {email} {password?}', function (string $email, ?string $password = null) {
    $user = App\Models\User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }

    // Prompt (hidden) when no password is given; the argument form is for web consoles without a prompt.
    $password ??= $this->secret('New password');
    if (strlen((string) $password) < 8) {
        $this->error('Password must be at least 8 characters.');

        return 1;
    }

    $user->changePassword($password);

    $this->info("Password changed for {$user->email}. Existing logins for this account were signed out.");
})->purpose('Set a user\'s password (hashed). Omit the password to be prompted for it.');

Artisan::command('user:admin {email} {--remove}', function (string $email) {
    $user = App\Models\User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email {$email}. Register at /register first.");

        return 1;
    }

    $user->forceFill(['is_admin' => ! $this->option('remove')])->save();
    $this->info($user->is_admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");
})->purpose('Make a registered user an admin (or --remove admin rights).');
