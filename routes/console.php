<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



Schedule::call(function () {
    $deleted = \App\Models\VerificationCode::where('expires_at', '<', now())->delete();
    \Illuminate\Support\Facades\Log::info("Cleaned {$deleted} expired verification codes");
})->daily();

Schedule::command('reminders:send-due-tasks')->hourly();

Schedule::command('trash:purge --days=30')->daily();
