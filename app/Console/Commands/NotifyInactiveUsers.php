<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyInactiveUsers extends Command{
    protected $signature = 'users:notify-inactive
                            {--days=30 : Nombre de jours d\'inactivité avant notification}
                            {--max-reminders=3 : Nombre maximum de rappels par utilisateur}
                            {--dry-run : Simuler sans envoyer}';

    protected $description = 'Notifie les utilisateurs inactifs depuis N jours (respecte les préférences)';

    public function handle(): int {
        $days         = (int) $this->option('days');
        $maxReminders = (int) $this->option('max-reminders');
        $dryRun       = $this->option('dry-run');
        $cutoff       = Carbon::now()->subDays($days);

        $this->info("Recherche des utilisateurs inactifs depuis {$days} jours...");
        if ($dryRun) $this->warn('Mode DRY-RUN activé — aucun message ne sera envoyé.');

        $users = User::where('activity_status', '!=', 'suspended')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($i) use ($cutoff) {
                    $i->whereNotNull('last_activity_at')->where('last_activity_at', '<=', $cutoff);
                })->orWhere(function ($i) use ($cutoff) {
                    $i->whereNull('last_activity_at')->whereNotNull('last_seen_at')->where('last_seen_at', '<=', $cutoff);
                })->orWhere(function ($i) use ($cutoff) {
                    $i->whereNull('last_activity_at')->whereNull('last_seen_at')->where('created_at', '<=', $cutoff);
                });
            })
            ->where(function ($q) {
                $q->whereNull('last_inactivity_reminder_at')
                  ->orWhere('last_inactivity_reminder_at', '<=', Carbon::now()->subDays(7));
            })
            ->where('inactivity_reminder_count', '<', $maxReminders)
            ->get();

        if ($users->isEmpty()) {
            $this->info('Aucun utilisateur inactif à notifier.');
            return self::SUCCESS;
        }

        $this->info("{$users->count()} utilisateur(s) trouvé(s).");

        $successCount = 0;
        $failCount    = 0;
        $bar          = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $sent = false;

            if (!$dryRun) {
                // NotificationDispatcher respecte les préférences canal + type "reminder"
                $sent = NotificationDispatcher::inactivityReminder(
                    $user,
                    $days,
                    $user->inactivity_reminder_count + 1
                );
            } else {
                $this->newLine();
                $this->line("-> [DRY-RUN] {$user->name} (" . ($user->email ?? $user->phone ?? 'N/A') . ")");
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
            usleep(200000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Envoyées : {$successCount}");
        if ($failCount > 0) $this->warn("Ignorées (préférences off ou échec) : {$failCount}");

        Log::info('[InactiveUsers] Terminé', [
            'days'    => $days,
            'total'   => $users->count(),
            'success' => $successCount,
            'failed'  => $failCount,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}