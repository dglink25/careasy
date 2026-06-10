<?php


namespace App\Services;

use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\RdvNotification;
use App\Notifications\InactivityReminderNotification;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher{
    
    // ─── Singletons ───────────────────────────────────────────────────────
    private static function sms(): SmsService {
        return app(SmsService::class);
    }

    private static function whatsapp(): WhatsAppService {
        return app(WhatsAppService::class);
    }

    public static function rdvPending(RendezVous $rdv): void {
        
        $prestataire = $rdv->prestataire;
        $client      = $rdv->client;

        // Créer un job dédié
        dispatch(fn() => self::sms()->notifyRdvPending($rdv))->afterResponse();
        dispatch(fn() => self::whatsapp()->notifyRdvPending($rdv))->afterResponse();

        // ── Prestataire ──────────────────────────────────────────────────
        if ($prestataire) {
            // In-app (toujours)
            self::sendInApp($prestataire, new RdvNotification($rdv, 'pending'));

            if (NotificationPreferences::canReceive($prestataire, 'rdv', 'sms')
                && NotificationPreferences::canReceiveViaChannel($prestataire, 'sms')) {
                self::safeSms(fn() => self::sms()->notifyRdvPending($rdv), 'rdvPending/sms/prestataire');
            }

            if (NotificationPreferences::canReceive($prestataire, 'whatsapp', 'rdv')
                && NotificationPreferences::canReceiveViaChannel($prestataire, 'whatsapp')) {
                self::safeWa(fn() => self::whatsapp()->notifyRdvPending($rdv), 'rdvPending/wa/prestataire');
            }
        }

        // ── Client ───────────────────────────────────────────────────────
        if ($client) {
            if (NotificationPreferences::canReceive($client, 'sms', 'rdv')
                && NotificationPreferences::canReceiveViaChannel($client, 'sms')) {
                self::safeSms(fn() => self::sms()->notifyRdvPending($rdv), 'rdvPending/sms/client');
            }

            if (NotificationPreferences::canReceive($client, 'whatsapp', 'rdv')
                && NotificationPreferences::canReceiveViaChannel($client, 'whatsapp')) {
                self::safeWa(fn() => self::whatsapp()->notifyRdvPending($rdv), 'rdvPending/wa/client');
            }
        }
    }

    public static function rdvConfirmed(RendezVous $rdv): void {
        $client = $rdv->client;
        if (!$client) return;

        self::sendInApp($client, new RdvNotification($rdv, 'confirmed'));

        if (NotificationPreferences::canReceive($client, 'sms', 'rdv')) {
            self::safeSms(fn() => self::sms()->notifyRdvConfirmed($rdv), 'rdvConfirmed/sms');
        }
        if (NotificationPreferences::canReceive($client, 'whatsapp', 'rdv')) {
            self::safeWa(fn() => self::whatsapp()->notifyRdvConfirmed($rdv), 'rdvConfirmed/wa');
        }
    }

    public static function rdvCancelled(RendezVous $rdv, int $cancelledById): void {
        $notifyUser = $cancelledById === $rdv->client_id
            ? $rdv->prestataire
            : $rdv->client;

        if (!$notifyUser) return;

        self::sendInApp($notifyUser, new RdvNotification($rdv, 'cancelled'));

        if (NotificationPreferences::canReceive($notifyUser, 'sms', 'rdv')) {
            self::safeSms(fn() => self::sms()->notifyRdvCancelled($rdv, $cancelledById), 'rdvCancelled/sms');
        }
        if (NotificationPreferences::canReceive($notifyUser, 'whatsapp', 'rdv')) {
            self::safeWa(fn() => self::whatsapp()->notifyRdvCancelled($rdv, $cancelledById), 'rdvCancelled/wa');
        }
    }

    public static function rdvCompleted(RendezVous $rdv): void  {
        $client = $rdv->client;
        if (!$client) return;

        self::sendInApp($client, new RdvNotification($rdv, 'completed'));

        if (NotificationPreferences::canReceive($client, 'sms', 'rdv')) {
            self::safeSms(fn() => self::sms()->notifyRdvCompleted($rdv), 'rdvCompleted/sms');
        }
        if (NotificationPreferences::canReceive($client, 'whatsapp', 'rdv')) {
            self::safeWa(fn() => self::whatsapp()->notifyRdvCompleted($rdv), 'rdvCompleted/wa');
        }
    }

    public static function rdvReminder(RendezVous $rdv): void {
        foreach ([$rdv->client, $rdv->prestataire] as $user) {
            if (!$user) continue;

            if (NotificationPreferences::canReceive($user, 'sms', 'reminder')) {
                self::safeSms(fn() => self::sms()->notifyRdvReminder($rdv), 'rdvReminder/sms');
            }
            if (NotificationPreferences::canReceive($user, 'whatsapp', 'reminder')) {
                self::safeWa(fn() => self::whatsapp()->notifyRdvReminder($rdv), 'rdvReminder/wa');
            }
        }
    }


    public static function inactivityReminder(User $user, int $days, int $reminderNumber = 1): bool  {
        $sent = false;

        // Email
        if (NotificationPreferences::canReceive($user, 'email', 'reminder')
            && !empty($user->email)) {
            try {
                $user->notify(new InactivityReminderNotification($days, $reminderNumber));
                $sent = true;
            } catch (\Exception $e) {
                Log::warning('[Dispatcher] Inactivity email failed: ' . $e->getMessage());
            }
        }

        // SMS
        if (NotificationPreferences::canReceive($user, 'sms', 'reminder')
            && !empty($user->phone)) {
            $firstName = explode(' ', trim($user->name))[0];
            $message   = "CarEasy - Bonjour {$firstName} ! "
                . "Vous n'avez pas utilisé votre compte depuis {$days} jours. "
                . "Reconnectez-vous pour trouver des prestataires auto près de chez vous.";
            $smsSent = self::sms()->sendMessage($user->phone, $message);
            if ($smsSent) $sent = true;
        }

        // WhatsApp
        if (NotificationPreferences::canReceive($user, 'whatsapp', 'reminder')
            && !empty($user->phone)) {
            $firstName = explode(' ', trim($user->name))[0];
            $message   = "*CarEasy vous manque, {$firstName} !*\n\n"
                . "Vous n'avez pas visité votre compte depuis *{$days} jours*.\n\n"
                . "Retrouvez tous vos prestataires automobile :\n"
                . "• Garages & mécaniciens\n• Vulcanisateurs\n• Lavage auto\n• Et bien plus...\n\n"
                . "Reconnectez-vous dès maintenant sur *CarEasy* !\n\n_L'équipe CarEasy_";
            $waSent = self::whatsapp()->sendMessage($user->phone, $message);
            if ($waSent) $sent = true;
        }

        return $sent;
    }


    public static function newService($service, array $recipients): void {
        $name      = $service->name;
        $entreprise = $service->entreprise?->name ?? '';

        foreach ($recipients as $user) {
            if (!NotificationPreferences::canReceiveType($user, 'new_service')) {
                continue;
            }

            // SMS
            if (NotificationPreferences::canReceiveViaChannel($user, 'sms')
                && !empty($user->phone)) {
                $msg = "CarEasy - Nouveau service : {$name}"
                    . ($entreprise ? " par {$entreprise}" : '')
                    . ". Découvrez-le sur CarEasy !";
                self::safeSms(fn() => self::sms()->sendMessage($user->phone, $msg), 'newService/sms');
            }

            // WhatsApp
            if (NotificationPreferences::canReceiveViaChannel($user, 'whatsapp')
                && !empty($user->phone)) {
                $msg = "*Nouveau service disponible - CarEasy*\n\n"
                    . "*{$name}*"
                    . ($entreprise ? " - {$entreprise}" : '') . "\n\n"
                    . "Découvrez et réservez ce service directement sur CarEasy !\n\n"
                    . "_L'équipe CarEasy_";
                self::safeWa(fn() => self::whatsapp()->sendMessage($user->phone, $msg), 'newService/wa');
            }
        }
    }


    private static function sendInApp(User $user, $notification): void {
      
        try {
            $user->notify($notification);
        } catch (\Exception $e) {
            Log::warning('[Dispatcher] In-app notification failed: ' . $e->getMessage());
        }
    }

    private static function safeSms(callable $fn, string $context): void {
        try { $fn(); } catch (\Exception $e) {
            Log::warning("[Dispatcher] SMS error ({$context}): " . $e->getMessage());
        }
    }

    private static function safeWa(callable $fn, string $context): void {
        try { $fn(); } catch (\Exception $e) {
            Log::warning("[Dispatcher] WhatsApp error ({$context}): " . $e->getMessage());
        }
    }
}