<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUserActivityStatus extends Command{
    protected $signature   = 'users:sync-activity-status
                              {--suspend-after=90 : Jours d\'inactivité avant suspension}';
    protected $description = 'Synchronise le statut d\'activité des utilisateurs (active/inactive/suspended)';

    public function handle(): int{
        $suspendAfter = (int) $this->option('suspend-after');
        $now          = Carbon::now();

        // ── 1. Réactiver les users qui se sont reconnectés récemment ─────────
        $reactivated = User::where('activity_status', 'inactive')
            ->where(function ($q) {
                $q->where('last_seen_at', '>=', Carbon::now()->subDays(1))
                  ->orWhere('last_activity_at', '>=', Carbon::now()->subDays(1));
            })
            ->update([
                'activity_status'           => 'active',
                'inactivity_reminder_count' => 0,
                'last_inactivity_reminder_at' => null,
            ]);

        // ── 2. Suspendre les users avec 3 rappels et toujours inactifs ───────
        $suspendCutoff = $now->copy()->subDays($suspendAfter);

        $suspended = User::where('activity_status', 'inactive')
            ->where('inactivity_reminder_count', '>=', 3)
            ->where(function ($q) use ($suspendCutoff) {
                $q->where('last_activity_at', '<=', $suspendCutoff)
                  ->orWhere(function ($inner) use ($suspendCutoff) {
                      $inner->whereNull('last_activity_at')
                            ->where('last_seen_at', '<=', $suspendCutoff);
                  });
            })
            ->update(['activity_status' => 'suspended']);

        $this->info("Réactivés  : {$reactivated} utilisateur(s)");
        $this->info("Suspendus  : {$suspended} utilisateur(s)");

        Log::info('[SyncActivityStatus]', [
            'reactivated' => $reactivated,
            'suspended'   => $suspended,
        ]);

        return self::SUCCESS;
    }
}