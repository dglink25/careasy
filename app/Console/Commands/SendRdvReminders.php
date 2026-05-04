<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendRdvReminders extends Command
{
    protected $signature   = 'rdv:send-reminders';
    protected $description = 'Envoie les rappels WhatsApp + SMS pour les RDV de demain';

    public function handle(WhatsAppService $whatsApp, SmsService $sms): int
    {
        $this->info('📱 Envoi des rappels RDV J-1 (WhatsApp + SMS)...');

        // ── WhatsApp ──────────────────────────────────────────────────────────
        try {
            $whatsApp->sendReminderForTomorrow();
            $this->info('Rappels WhatsApp envoyés.');
        } catch (\Exception $e) {
            $this->warn('WhatsApp échoué : ' . $e->getMessage());
        }

        // ── SMS ───────────────────────────────────────────────────────────────
        try {
            $sms->sendReminderForTomorrow();
            $this->info('Rappels SMS envoyés.');
        } catch (\Exception $e) {
            $this->warn('SMS échoué : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}