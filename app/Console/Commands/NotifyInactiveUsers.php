<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InactivityReminderNotification;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyInactiveUsers extends Command{
    protected $signature = 'users:notify-inactive
                            {--days=30 : Nombre de jours d\'inactivité avant notification}
                            {--max-reminders=3 : Nombre maximum de rappels par utilisateur}
                            {--dry-run : Simuler sans envoyer}';

    protected $description = 'Notifie les utilisateurs inactifs depuis N jours (email, SMS ou WhatsApp)';

    public function __construct(
        private readonly SmsService $smsService,
        private readonly WhatsAppService $whatsAppService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days          = (int) $this->option('days');
        $maxReminders  = (int) $this->option('max-reminders');
        $dryRun        = $this->option('dry-run');
        $cutoff        = Carbon::now()->subDays($days);

        $this->info("Recherche des utilisateurs inactifs depuis {$days} jours...");

        if ($dryRun) {
            $this->warn('Mode DRY-RUN activé — aucun message ne sera envoyé.');
        }

        // ── Requête : utilisateurs inactifs ──────────────────────────────────
        $users = User::where('activity_status', '!=', 'suspended')
            ->where(function ($q) use ($cutoff) {

                $q->where(function ($inner) use ($cutoff) {

                    // Priorité : last_activity_at
                    $inner->whereNotNull('last_activity_at')
                        ->where('last_activity_at', '<=', $cutoff);

                })->orWhere(function ($inner) use ($cutoff) {

                    // Fallback : last_seen_at
                    $inner->whereNull('last_activity_at')
                        ->whereNotNull('last_seen_at')
                        ->where('last_seen_at', '<=', $cutoff);

                })->orWhere(function ($inner) use ($cutoff) {

                    // Si jamais connecté : created_at
                    $inner->whereNull('last_activity_at')
                        ->whereNull('last_seen_at')
                        ->where('created_at', '<=', $cutoff);
                });
            })

            // Ne pas renvoyer avant 7 jours
            ->where(function ($q) {
                $q->whereNull('last_inactivity_reminder_at')
                    ->orWhere(
                        'last_inactivity_reminder_at',
                        '<=',
                        Carbon::now()->subDays(7)
                    );
            })

            // Nombre max de rappels
            ->where('inactivity_reminder_count', '<', $maxReminders)

            ->get();

        if ($users->isEmpty()) {
            $this->info('Aucun utilisateur inactif à notifier.');
            return self::SUCCESS;
        }

        $this->info("{$users->count()} utilisateur(s) inactif(s) trouvé(s).");

        $successCount = 0;
        $failCount    = 0;

        $bar = $this->output->createProgressBar($users->count());

        $bar->start();

        foreach ($users as $user) {

            $sent = false;

            if (!$dryRun) {

                $sent = $this->notifyUser($user, $days);

            } else {

                // Simulation
                $channel = $this->resolveChannel($user);

                $this->newLine();

                $this->line(
                    "-> [{$channel}] {$user->name} (" .
                    ($user->email ?? $user->phone ?? 'N/A') .
                    ")"
                );

                $sent = true;
            }

            if ($sent) {

                if (!$dryRun) {

                    $user->update([
                        'last_inactivity_reminder_at' => now(),
                        'inactivity_reminder_count'   => $user->inactivity_reminder_count + 1,
                        'activity_status'             => 'inactive',
                    ]);
                }

                $successCount++;

            } else {

                $failCount++;
            }

            $bar->advance();

            // Petite pause anti flood
            usleep(200000);
        }

        $bar->finish();

        $this->newLine(2);

        $this->info("Notifications envoyées : {$successCount}");

        if ($failCount > 0) {
            $this->warn("Échecs : {$failCount}");
        }

        Log::info('[InactiveUsers] Cron terminé', [
            'days'     => $days,
            'total'    => $users->count(),
            'success'  => $successCount,
            'failed'   => $failCount,
            'dry_run'  => $dryRun,
        ]);

        return self::SUCCESS;
    }

    // ─── Notification principale ─────────────────────────────────────────────
    private function notifyUser(User $user, int $days): bool
    {
        $channel = $this->resolveChannel($user);

        try {

            return match ($channel) {

                'email'    => $this->sendEmail($user, $days),
                'sms'      => $this->sendSms($user, $days),
                'whatsapp' => $this->sendWhatsApp($user, $days),

                default => false,
            };

        } catch (\Exception $e) {

            Log::warning("[InactiveUsers] Échec notification user #{$user->id}", [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ─── Déterminer le canal ─────────────────────────────────────────────────
    private function resolveChannel(User $user): string
    {
        if (!empty($user->email)) {
            return 'email';
        }

        if (!empty($user->phone)) {
            return 'sms';
        }

        return 'none';
    }

    // ─── Email ───────────────────────────────────────────────────────────────
    private function sendEmail(User $user, int $days): bool
    {
        try {

            $user->notify(
                new InactivityReminderNotification(
                    $days,
                    $user->inactivity_reminder_count + 1
                )
            );

            Log::info("[InactiveUsers] Email envoyé → {$user->email}");

            return true;

        } catch (\Exception $e) {

            Log::warning(
                "[InactiveUsers] Email échoué → {$user->email} : {$e->getMessage()}"
            );

            return false;
        }
    }

    // ─── SMS ─────────────────────────────────────────────────────────────────
    private function sendSms(User $user, int $days): bool
    {
        $firstName = explode(' ', trim($user->name))[0];

        $message = "CarEasy - Bonjour {$firstName} ! "
            . "Vous n'avez pas utilisé votre compte depuis {$days} jours. "
            . "Reconnectez-vous pour trouver des prestataires auto près de chez vous. "
            . "Besoin d'aide ? Contactez-nous.";

        $smsSent = $this->smsService->sendMessage(
            $user->phone,
            $message
        );

        if ($smsSent) {

            Log::info("[InactiveUsers] SMS envoyé → {$user->phone}");

            return true;
        }

        // Fallback WhatsApp
        Log::warning(
            "[InactiveUsers] SMS échoué → {$user->phone}, tentative WhatsApp..."
        );

        return $this->sendWhatsApp($user, $days);
    }

    // ─── WhatsApp ────────────────────────────────────────────────────────────
    private function sendWhatsApp(User $user, int $days): bool
    {
        $firstName = explode(' ', trim($user->name))[0];

        $message = "*CarEasy vous manque, {$firstName} !*\n\n"
            . "Vous n'avez pas visité votre compte depuis *{$days} jours*.\n\n"
            . "Retrouvez tous vos prestataires automobile :\n"
            . "• Garages & mécaniciens\n"
            . "• Vulcanisateurs\n"
            . "• Lavage auto\n"
            . "• Et bien plus...\n\n"
            . "Reconnectez-vous dès maintenant sur *CarEasy* !\n\n"
            . "_L'équipe CarEasy_";

        $sent = $this->whatsAppService->sendMessage(
            $user->phone,
            $message
        );

        if ($sent) {

            Log::info("[InactiveUsers] WhatsApp envoyé → {$user->phone}");

        } else {

            Log::error("[InactiveUsers] WhatsApp échoué → {$user->phone}");
        }

        return $sent;
    }
}