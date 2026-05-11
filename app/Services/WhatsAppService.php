<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WhatsAppService
{
    protected string $baseUrl;
    protected string $secret;
    protected int    $timeout;

    public function __construct(){
        $this->baseUrl = config('whatsapp.gateway_url', 'https://campus357.alwaysdata.net');
        $this->secret  = config('whatsapp.api_secret', '35abced573ff026656aaf2e6e1dec87a22b1ea51c3cc27417c5b0fad7b54a67b');
        $this->timeout = 15;
    }

    private function headers(): array{
        return [
            'Content-Type' => 'application/json',
            'X-Api-Secret' => $this->secret,
        ];
    }

    // ─── Vérifier disponibilité avec retry ───────────────────────────────────
    public function isAvailable(): bool {
        try {
            $response = Http::timeout(8)->get("{$this->baseUrl}/status");
            return $response->ok() && $response->json('ready') === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── Envoyer avec retry automatique (3 tentatives) ───────────────────────
    public function sendMessage(string $phone, string $message): bool{
        // Normaliser AVANT d'envoyer
        $normalized = $this->normalizePhone($phone);

        if (!$normalized) {
            Log::warning('[WhatsApp] Numéro non normalisable — envoi ignoré', [
                'phone_raw' => substr($phone, 0, 6) . '***',
            ]);
            return false;
        }

        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers())
                    ->post("{$this->baseUrl}/send", [
                        'phone'   => $normalized,           // ← utiliser $normalized
                        'message' => $message,
                    ]);

                if ($response->successful() && $response->json('success')) {
                    Log::info("[WhatsApp] Envoyé à {$normalized} (tentative {$attempt})");
                    return true;
                }

                $errorMsg = $response->json('message') ?? '';
                if (str_contains($errorMsg, 'non connecté') && $attempt < $maxRetries) {
                    Log::warning("WhatsApp non connecté, retry {$attempt}/{$maxRetries}...");
                    sleep(2);
                    continue;
                }

                Log::warning("[WhatsApp] Échec à {$normalized}", [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'attempt' => $attempt,
                ]);

            } catch (\Exception $e) {
                Log::warning("[WhatsApp] Erreur tentative {$attempt}: {$e->getMessage()}");
                if ($attempt < $maxRetries) sleep(2);
            }
        }

        Log::error("[WhatsApp] Impossible d'envoyer à {$normalized} après {$maxRetries} tentatives");
        return false;
    }

    // ─── Normaliser vers le format WhatsApp : +229XXXXXXXX (8 chiffres locaux) ──
    private function normalizePhone(string $phone): ?string {
        $digits = preg_replace('/\D/', '', $phone);
        if (empty($digits)) return null;

        // Retirer le code pays Bénin s'il est présent
        if (str_starts_with($digits, '229')) {
            $digits = substr($digits, 3);
        }

        // Format local 10 chiffres commençant par 01 → retirer 01 → 8 chiffres
        if (preg_match('/^01(\d{8})$/', $digits, $m)) {
            return '+229' . $m[1];                      // +22997035431 ✅
        }

        // Format local 8 chiffres → déjà correct pour WhatsApp
        if (preg_match('/^\d{8}$/', $digits)) {
            return '+229' . $digits;                    // +22997035431 ✅
        }

        Log::warning('[WhatsApp] Numéro non normalisable', [
            'phone_raw' => substr($phone, 0, 6) . '***',
        ]);
        return null;
    }

    // ─── Envoi en masse ───────────────────────────────────────────────────────
    public function sendBulk(array $recipients): array {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/send-bulk", [
                    'recipients' => $recipients,
                ]);

            return $response->successful()
                ? $response->json()
                : ['success' => false, 'sent' => 0];

        } catch (\Exception $e) {
            Log::error('WhatsApp bulk erreur: ' . $e->getMessage());
            return ['success' => false, 'sent' => 0];
        }
    }

    // ─── Notification RDV complète ────────────────────────────────────────────
    public function sendRdvNotification(array $rdvData, string $event, array $recipients): bool {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/send-rdv", [
                    'rdv'        => $rdvData,
                    'event'      => $event,
                    'recipients' => $recipients,
                ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("WhatsApp RDV '{$event}' erreur: " . $e->getMessage());
            return false;
        }
    }

    // ─── Notification inscription par téléphone ───────────────────────────────
    public function notifyRegistration(string $phone, string $name): void {
        $firstName = explode(' ', $name)[0];
        $message   = "*Bienvenue sur CarEasy, {$firstName} !*\n\n"
                   . "Votre compte a été créé avec succès. "
                   . "Trouvez des prestataires automobile près de chez vous et prenez rendez-vous facilement.\n\n"
                   . "_L'équipe CarEasy_";

        $this->sendMessage($phone, $message);
    }

    // ─── RDV créé → notifie prestataire + client ──────────────────────────────
    public function notifyRdvPending(\App\Models\RendezVous $rdv): void {
        $rdvData    = $this->buildRdvData($rdv);
        $date       = $this->formatDate($rdv->date);
        $heure      = $this->formatTime($rdv->start_time);
        $service    = $rdv->service?->name    ?? 'votre service';
        $entreprise = $rdv->entreprise?->name ?? '';
        $client     = $rdv->client?->name     ?? 'Un client';

        // Notifier le prestataire
        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $msgPrestataire = "*Nouvelle demande de RDV - CarEasy*\n\n"
                . "*Client :* {$client}\n"
                . "*Service :* {$service}\n"
                . "*Date :* {$date}\n"
                . "*Heure :* {$heure}\n\n"
                . "Connectez-vous sur CarEasy pour confirmer ou refuser ce rendez-vous.";

            $this->sendMessage($rdv->prestataire->phone, $msgPrestataire);
        }

        // Notifier le client
        if ($rdv->client && !empty($rdv->client->phone)) {
            $msgClient = "*Demande de RDV envoyée - CarEasy*\n\n"
                . "*Service :* {$service}"
                . ($entreprise ? " - {$entreprise}" : '') . "\n"
                . "*Date :* {$date}\n"
                . "*Heure :* {$heure}\n\n"
                . "Votre demande est en attente de confirmation par le prestataire. "
                . "Vous serez notifié dès qu'elle sera traitée.";

            $this->sendMessage($rdv->client->phone, $msgClient);
        }
    }

    // ─── RDV confirmé → notifie client ───────────────────────────────────────
    public function notifyRdvConfirmed(\App\Models\RendezVous $rdv): void {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'Service';
        $ent     = $rdv->entreprise?->name ?? '';

        $message = "*RDV Confirmé - CarEasy*\n\n"
            . "*Service :* {$service}"
            . ($ent ? " - {$ent}" : '') . "\n"
            . "*Date :* {$date}\n"
            . "*Heure :* {$heure}\n\n"
            . "Votre rendez-vous est confirmé ! N'oubliez pas d'être à l'heure \n\n"
            . "_CarEasy_";

        $this->sendMessage($rdv->client->phone, $message);
    }

    // ─── RDV annulé → notifie l'autre partie ─────────────────────────────────
    public function notifyRdvCancelled(\App\Models\RendezVous $rdv, int $cancelledById): void {
        $date    = $this->formatDate($rdv->date);
        $service = $rdv->service?->name ?? 'Service';
        $raison  = $rdv->prestataire_notes ?? null;

        $notifyUser = $cancelledById === $rdv->client_id ? $rdv->prestataire : $rdv->client;

        if (!$notifyUser || empty($notifyUser->phone)) return;

        $message = "*RDV Annulé - CarEasy*\n\n"
            . "*Service :* {$service}\n"
            . "*Date :* {$date}\n"
            . ($raison ? "*Motif :* {$raison}\n" : '')
            . "\nVous pouvez reprendre un rendez-vous directement sur CarEasy.\n\n"
            . " _CarEasy_";

        $this->sendMessage($notifyUser->phone, $message);
    }

    // ─── RDV terminé → notifie client ────────────────────────────────────────
    public function notifyRdvCompleted(\App\Models\RendezVous $rdv): void  {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $service = $rdv->service?->name    ?? 'Service';
        $ent     = $rdv->entreprise?->name ?? '';

        $message = "*Service terminé - CarEasy*\n\n"
            . "Merci d'avoir utilisé *{$service}"
            . ($ent ? " - {$ent}" : '') . "* !\n\n"
            . "Votre avis nous aide à améliorer CarEasy. "
            . "Prenez un moment pour noter ce service directement dans l'application.\n\n"
            . "_L'équipe CarEasy_";

        $this->sendMessage($rdv->client->phone, $message);
    }

    // ─── Rappel RDV J-1 → notifie client et prestataire ─────────────────────
    public function notifyRdvReminder(\App\Models\RendezVous $rdv): void {
        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'Service';
        $ent     = $rdv->entreprise?->name ?? '';
        $client  = $rdv->client?->name     ?? 'Le client';

        // Rappel pour le client
        if ($rdv->client && !empty($rdv->client->phone)) {
            $msgClient = "*Rappel RDV demain - CarEasy*\n\n"
                . "*Service :* {$service}"
                . ($ent ? " - {$ent}" : '') . "\n"
                . "*Date :* {$date}\n"
                . "*Heure :* {$heure}\n\n"
                . "N'oubliez pas votre rendez-vous de demain ! \n\n"
                . "_CarEasy_";

            $this->sendMessage($rdv->client->phone, $msgClient);
        }

        // Rappel pour le prestataire
        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $msgPrestataire = "*Rappel RDV demain - CarEasy*\n\n"
                . "*Client :* {$client}\n"
                . "*Service :* {$service}\n"
                . "*Date :* {$date}\n"
                . "*Heure :* {$heure}\n\n"
                . "Pensez à vous préparer pour ce rendez-vous demain.\n\n"
                . "_CarEasy_";

            $this->sendMessage($rdv->prestataire->phone, $msgPrestataire);
        }
    }

    // ─── Rappels J-1 (appelé par le scheduler) ───────────────────────────────
    public function sendReminderForTomorrow(): void {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $rdvs = \App\Models\RendezVous::with(['client', 'prestataire', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();

        foreach ($rdvs as $rdv) {
            $this->notifyRdvReminder($rdv);
            usleep(500000); // 0.5s entre chaque envoi
        }

        Log::info("WhatsApp rappels J-1 envoyés", ['count' => $rdvs->count()]);
    }

    // ─── Construire les données RDV ───────────────────────────────────────────
    private function buildRdvData(\App\Models\RendezVous $rdv): array {
        return [
            'id'              => $rdv->id,
            'date'            => $rdv->date,
            'start_time'      => $rdv->start_time ? substr($rdv->start_time, 0, 5) : '',
            'end_time'        => $rdv->end_time   ? substr($rdv->end_time, 0, 5)   : '',
            'service_name'    => $rdv->service?->name    ?? 'Service',
            'entreprise_name' => $rdv->entreprise?->name ?? 'Entreprise',
            'client_name'     => $rdv->client?->name     ?? 'Client',
            'client_notes'    => $rdv->client_notes      ?? '',
            'cancel_reason'   => $rdv->prestataire_notes ?? '',
        ];
    }

    private function formatDate($date): string{
        if (!$date) return '';
        try {
            return \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    private function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }
}