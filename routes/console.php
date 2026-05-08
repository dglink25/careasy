<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



Schedule::command('rdv:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('[Scheduler] rdv:send-reminders exécuté avec succès à 18h00.');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Scheduler] rdv:send-reminders a échoué à 18h00.');
    });


Schedule::command('qr:purge --days=7')
    ->dailyAt('00:00')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping();