<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendRdvReminders extends Command{
    protected $signature   = 'rdv:send-reminders';
    protected $description = 'Envoie les rappels WhatsApp + SMS pour les RDV de demain';

    public function handle(): int {
        $this->info('Envoi des rappels RDV J-1...');
 
        $tomorrow = now()->addDay()->format('Y-m-d');
        $rdvs = \App\Models\RendezVous::with(['client', 'prestataire', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();
 
        $sent = 0;
        foreach ($rdvs as $rdv) {
            try {
                \App\Services\NotificationDispatcher::rdvReminder($rdv);
                $sent++;
            } catch (\Exception $e) {
                $this->warn('Rappel échoué RDV #' . $rdv->id . ': ' . $e->getMessage());
            }
            usleep(500_000);
        }
 
        $this->info("Rappels envoyés : {$sent}/{$rdvs->count()}");
        return Command::SUCCESS;
    }
}