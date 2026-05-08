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
    ->onSuccess(fn() => \Illuminate\Support\Facades\Log::info('[Cron] rdv:send-reminders OK'))
    ->onFailure(fn() => \Illuminate\Support\Facades\Log::error('[Cron] rdv:send-reminders FAILED'));


Schedule::command('qr:purge --days=7')
    ->dailyAt('00:00')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping();


Schedule::command('users:sync-activity-status --suspend-after=90')
    ->dailyAt('07:00')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Illuminate\Support\Facades\Log::info('[Cron] sync-activity-status OK'))
    ->onFailure(fn() => \Illuminate\Support\Facades\Log::error('[Cron] sync-activity-status FAILED'));

Schedule::command('users:notify-inactive --days=30 --max-reminders=3')
    ->weeklyOn(1, '09:00')   // Lundi 09h00
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Illuminate\Support\Facades\Log::info('[Cron] notify-inactive (lundi) OK'))
    ->onFailure(fn() => \Illuminate\Support\Facades\Log::error('[Cron] notify-inactive (lundi) FAILED'));

Schedule::command('users:notify-inactive --days=30 --max-reminders=3')
    ->weeklyOn(4, '09:00')   // Jeudi 09h00
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Illuminate\Support\Facades\Log::info('[Cron] notify-inactive (jeudi) OK'))
    ->onFailure(fn() => \Illuminate\Support\Facades\Log::error('[Cron] notify-inactive (jeudi) FAILED'));