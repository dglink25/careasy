<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendRdvReminders extends Command
{
    protected $signature   = 'rdv:send-reminders';
    protected $description = 'Envoie les rappels WhatsApp pour les RDV de demain';

    public function handle(WhatsAppService $whatsApp): int{
        $this->info('📱 Envoi des rappels WhatsApp RDV J-1...');

        try {
            $whatsApp->sendReminderForTomorrow();
            $this->info('Rappels envoyés avec succès.');
        } 
        catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
