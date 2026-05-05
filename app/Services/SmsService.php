<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SmsService{
    protected string $baseUrl;
    protected string $user;
    protected string $pass;
    protected string $senderPhone;
    protected bool   $enabled;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              
    protected int    $timeout;

    public function __construct(){                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      
        $this->baseUrl     = rtrim(config('sms.gateway_url', ''), '/');
        $this->user        = config('sms.gateway_user', 'admin');
        $this->pass        = config('sms.gateway_pass', '');
        $this->senderPhone = config('sms.sender_phone', '+2290199955078');
        $this->enabled     = config('sms.enabled', true);
        $this->timeout     = 15;                                                                                                                                                                                                                                                                                        
    }

    // ─── Vérifier que la gateway est joignable ────────────────────────────────                                                                           
    public function isAvailable(): bool {
        if (!$this->enabled || empty($this->baseUrl)) {                                                                                                                                                                                                                                                                                                                                                                                                                 
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withBasicAuth($this->user, $this->pass)
                ->get("{$this->baseUrl}/api/v1/health");

            return $response->ok();
        } catch (\Exception $e) {
            Log::warning('[SMS] Gateway non joignable : ' . $e->getMessage());
            return false;
        }
    }

    // ─── Envoyer un SMS simple ────────────────────────────────────────────────
    public function sendMessage(string $phone, string $message): bool{
        if (!$this->enabled) {
            Log::info('[SMS] Désactivé — message non envoyé à ' . $phone);
            return false;
        }

        $phone = $this->normalizePhone($phone);

        if (!$phone) {
            Log::warning('[SMS] Numéro invalide, envoi ignoré');
            return false;
        }

        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withBasicAuth($this->user, $this->pass)
                    ->post("{$this->baseUrl}/api/v1/message", [
                        'message'       => $this->truncate($message),
                        'phoneNumbers'  => [$phone],
                        'skipPhoneValidation' => false,
                        'withDeliveryReport'  => false,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info("[SMS] Envoyé à {$phone}", [
                        'id'      => $data['id'] ?? null,
                        'attempt' => $attempt,
                    ]);
                    return true;
                }

                Log::warning("[SMS] Échec tentative {$attempt}/{$maxRetries} vers {$phone}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::warning("[SMS] Exception tentative {$attempt} : " . $e->getMessage());
                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            }
        }

        Log::error("[SMS] Impossible d'envoyer à {$phone} après {$maxRetries} tentatives");
        return false;
    }

    // ─── Envoyer à plusieurs destinataires en un seul appel ──────────────────
    public function sendBulk(array $recipients): bool {
        if (!$this->enabled || empty($recipients)) {
            return false;
        }

        // Regrouper par message identique pour minimiser les appels
        $grouped = [];
        foreach ($recipients as $r) {
            $phone = $this->normalizePhone($r['phone'] ?? '');
            if (!$phone) continue;
            $msg = $this->truncate($r['message'] ?? '');
            $grouped[$msg][] = $phone;
        }

        $allOk = true;
        foreach ($grouped as $message => $phones) {
            try {
                $response = Http::timeout(30)
                    ->withBasicAuth($this->user, $this->pass)
                    ->post("{$this->baseUrl}/api/v1/message", [
                        'message'            => $message,
                        'phoneNumbers'       => $phones,
                        'withDeliveryReport' => false,
                    ]);

                if (!$response->successful()) {
                    Log::warning('[SMS] Bulk partiel échoué', [
                        'phones' => $phones,
                        'status' => $response->status(),
                    ]);
                    $allOk = false;
                }
            } catch (\Exception $e) {
                Log::error('[SMS] Exception bulk : ' . $e->getMessage());
                $allOk = false;
            }
        }

        return $allOk;
    }

    // ─── Templates RDV ────────────────────────────────────────────────────────

    public function notifyRdvPending(\App\Models\RendezVous $rdv): void  {
        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'votre service';
        $ent     = $rdv->entreprise?->name ?? '';

        // SMS prestataire
        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $this->sendMessage(
                $rdv->prestataire->phone,
                "CarEasy Nouvelle demande de RDV\n" .
                "Client : {$rdv->client?->name}\n" .
                "Service : {$service}\n" .
                "Date : {$date} à {$heure}\n" .
                "Connectez-vous pour confirmer."
            );
        }

        // SMS client
        if ($rdv->client && !empty($rdv->client->phone)) {
            $this->sendMessage(
                $rdv->client->phone,
                "CarEasy Demande envoyée\n" .
                "Service : {$service}" . ($ent ? " — {$ent}" : '') . "\n" .
                "Date : {$date} à {$heure}\n" .
                "Vous recevrez une confirmation bientôt."
            );
        }
    }

    public function notifyRdvConfirmed(\App\Models\RendezVous $rdv): void {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'votre service';
        $ent     = $rdv->entreprise?->name ?? '';

        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy 🎉 RDV confirmé !\n" .
            "Service : {$service}" . ($ent ? " — {$ent}" : '') . "\n" .
            "Date : {$date} à {$heure}\n" .
            "Soyez ponctuel. À bientôt !"
        );
    }

    public function notifyRdvCancelled(\App\Models\RendezVous $rdv, int $cancelledById): void {
        $notifyUser = $cancelledById === $rdv->client_id ? $rdv->prestataire : $rdv->client;
        if (!$notifyUser || empty($notifyUser->phone)) return;

        $date    = $this->formatDate($rdv->date);
        $service = $rdv->service?->name ?? 'votre service';
        $raison  = $rdv->prestataire_notes ? "\nMotif : {$rdv->prestataire_notes}" : '';

        $this->sendMessage(
            $notifyUser->phone,
            "CarEasy ❌ RDV annulé\n" .
            "Service : {$service}\n" .
            "Date annulée : {$date}{$raison}\n" .
            "Reprenez RDV sur CarEasy."
        );
    }

    public function notifyRdvCompleted(\App\Models\RendezVous $rdv): void {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $service = $rdv->service?->name ?? 'votre service';

        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy Service terminé !\n" .
            "Merci d'avoir utilisé : {$service}\n" .
            "Laissez un avis sur CarEasy — ça aide beaucoup !"
        );
    }

    public function notifyRdvReminder(\App\Models\RendezVous $rdv): void {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'votre service';
        $ent     = $rdv->entreprise?->name ?? '';

        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy Rappel RDV demain\n" .
            "Service : {$service}" . ($ent ? " — {$ent}" : '') . "\n" .
            "Date : {$date} à {$heure}\n" .
            "N'oubliez pas !"
        );
    }

    // ─── Template inscription ─────────────────────────────────────────────────
    public function notifyRegistration(string $phone, string $name): void {
        $firstName = explode(' ', $name)[0];
        $this->sendMessage(
            $phone,
            "CarEasy Bienvenue {$firstName} !\n" .
            "Votre compte est créé. Trouvez des prestataires auto près de chez vous."
        );
    }

    // ─── Template validation entreprise ──────────────────────────────────────
    public function notifyEntrepriseApproved(\App\Models\Entreprise $entreprise): void{
        $user = $entreprise->prestataire;
        if (!$user || empty($user->phone)) return;

        $this->sendMessage(
            $user->phone,
            "CarEasy 🎉 Félicitations !\n" .
            "Votre entreprise « {$entreprise->name} » est validée.\n" .
            "Période d'essai 30j activée. Connectez-vous pour créer vos services !"
        );
    }

    public function notifyEntrepriseRejected(\App\Models\Entreprise $entreprise, ?string $reason = null): void {
        $user = $entreprise->prestataire;
        if (!$user || empty($user->phone)) return;

        $msg = "CarEasy Demande refusée\n" .
               "Entreprise : {$entreprise->name}\n";
        if ($reason) {
            $msg .= "Motif : " . $this->truncate($reason, 80) . "\n";
        }
        $msg .= "Corrigez et soumettez à nouveau.";

        $this->sendMessage($user->phone, $msg);
    }

    // ─── OTP par SMS ──────────────────────────────────────────────────────────
    public function sendOtp(string $phone, string $code, string $name = ''): bool {
        $firstName = $name ? explode(' ', $name)[0] : '';
        $greeting  = $firstName ? "Bonjour {$firstName}, " : '';

        return $this->sendMessage(
            $phone,
            "CarEasy {$greeting}votre code de vérification :\n" .
            "{$code}\n" .
            "Valide 5 min. Ne le partagez jamais."
        );
    }

    // ─── Rappels J-1 (appelé par le scheduler) ───────────────────────────────
    public function sendReminderForTomorrow(): void{
        $tomorrow = now()->addDay()->format('Y-m-d');

        $rdvs = \App\Models\RendezVous::with(['client', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();

        foreach ($rdvs as $rdv) {
            $this->notifyRdvReminder($rdv);
            usleep(500_000); // 0.5s entre chaque SMS pour ne pas saturer
        }

        Log::info('[SMS] Rappels J-1 envoyés', ['count' => $rdvs->count()]);
    }


    private function normalizePhone(string $phone): ?string {
        // Supprimer espaces, tirets, parenthèses
        $clean = preg_replace('/[^\d+]/', '', $phone);

        if (empty($clean)) return null;

        // Béninois sans indicatif (8 chiffres) → +229XXXXXXXX
        if (preg_match('/^\d{8}$/', $clean)) {
            return '+229' . $clean;
        }

        // Avec indicatif sans + (22900000000)
        if (preg_match('/^229\d{8}$/', $clean)) {
            return '+' . $clean;
        }

        // Déjà formaté avec +
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        return '+' . $clean;
    }

    private function truncate(string $text, int $max = 160): string {
        // SMS standard = 160 chars. Au-delà, l'app envoie un long SMS (concaténé).
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max - 3) . '...';
    }

    private function formatDate($date): string {
        if (!$date) return '';
        try {
            return \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    private function formatTime(?string $time): string {
        if (!$time) return '';
        return substr($time, 0, 5);
    }
}