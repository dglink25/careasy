<?php

namespace App\Console\Commands;

use App\Models\QrLoginToken;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeExpiredQrTokens extends Command{
    protected $signature   = 'qr:purge
                              {--days=7 : Supprimer les tokens expirés depuis N jours}';

    protected $description = 'Purge les QR tokens expirés ou utilisés depuis plus de N jours';

    public function handle(): int {
        $days = (int) $this->option('days');

        // 1. Marquer comme expirés les tokens pending dépassés
        $marked = QrLoginToken::where('status', 'pending')
                              ->where('expires_at', '<', Carbon::now())
                              ->update(['status' => 'expired']);

        // 2. Supprimer définitivement les anciens tokens (expirés ou utilisés)
        $deleted = QrLoginToken::whereIn('status', ['expired', 'used'])
                               ->where('updated_at', '<', Carbon::now()->subDays($days))
                               ->delete();

        $this->info("QR tokens : $marked marqués expirés, $deleted supprimés définitivement.");

        return self::SUCCESS;
    }
}