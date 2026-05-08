<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $baseUrl;
    protected string $user;
    protected string $pass;
    protected string $senderPhone;
    protected bool   $enabled;
    protected int    $timeout;

    public function __construct()
    {
        $this->baseUrl     = rtrim(config('sms.gateway_url', ''), '/');
        $this->user        = config('sms.gateway_user', 'admin');
        $this->pass        = config('sms.gateway_pass', '');
        $this->senderPhone = config('sms.sender_phone', '+2290194119476');
        $this->enabled     = config('sms.enabled', true);
        $this->timeout     = 15;
    }

 

    private function isCloudMode(): bool
{
    return true;
}

    private function healthEndpoint(): string
    {
        return $this->isCloudMode()
            ? $this->baseUrl
            : $this->baseUrl . '/health';
    }

    private function messageEndpoint(): string
    {
        return $this->isCloudMode()
            ? $this->baseUrl . '/messages'  // pluriel cloud
            : $this->baseUrl . '/message';  // singulier local
    }

    // ─── Vérifier que la gateway est joignable ────────────────────────────────
    public function isAvailable(): bool
    {
        if (!$this->enabled || empty($this->baseUrl)) {
            Log::debug('[SMS] isAvailable=false : désactivé ou URL vide');
            return false;
        }

        try {
            $url      = $this->healthEndpoint();
            $response = Http::timeout(5)
                ->withBasicAuth($this->user, $this->pass)
                ->get($url);

            $ok = $response->successful();

            Log::debug('[SMS] isAvailable check', [
                'url'    => $url,
                'mode'   => $this->isCloudMode() ? 'cloud' : 'local',
                'status' => $response->status(),
                'body'   => $response->body(),
                'result' => $ok ? 'ONLINE' : 'OFFLINE',
            ]);

            return $ok;

        } catch (\Exception $e) {
            Log::warning('[SMS] Gateway non joignable : ' . $e->getMessage());
            return false;
        }
    }

    // ─── Envoyer un SMS simple ────────────────────────────────────────────────
    public function sendMessage(string $phone, string $message): bool
    {
        if (!$this->enabled) {
            Log::info('[SMS] Désactivé — message non envoyé à ' . $phone);
            return false;
        }

        $normalized = $this->normalizePhone($phone);

        if (!$normalized) {
            Log::warning('[SMS] Numéro invalide ignoré', ['phone_raw' => $phone]);
            return false;
        }

        $endpoint = $this->messageEndpoint();
        $payload  = [
            'message'      => $this->truncate($message),
            'phoneNumbers' => [$normalized],
        ];

        Log::info('[SMS] Tentative envoi', [
            'endpoint' => $endpoint,
            'mode'     => $this->isCloudMode() ? 'cloud' : 'local',
            'phone'    => $normalized,
            'message'  => mb_substr($message, 0, 50) . '...',
        ]);

        $maxRetries = 10;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withBasicAuth($this->user, $this->pass)
                    ->post($endpoint, $payload);

                Log::info("[SMS] Réponse tentative {$attempt}/{$maxRetries}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                if ($response->successful() && in_array($response->status(), [200, 202])) {
                    $data = $response->json();
                    Log::info("[SMS] SMS accepté par la gateway", [
                        'state' => $data['state'] ?? null,
                    ]);
                    Log::info("[SMS] SMS envoyé à {$normalized}", [
                        'id'      => $data['id'] ?? ($data['ids'][0] ?? null),
                        'state'   => $data['state'] ?? ($data['status'] ?? null),
                        'attempt' => $attempt,
                    ]);
                    return true;
                }

                // 404 = mauvais endpoint — inutile de réessayer
                if ($response->status() === 404) {
                    Log::error('[SMS] 404 — Mauvais endpoint : ' . $endpoint, [
                        'baseUrl' => $this->baseUrl,
                        'mode'    => $this->isCloudMode() ? 'cloud' : 'local',
                        'tip'     => 'Si URL=api.sms-gate.app → mode cloud (POST /messages). Sinon mode local (POST /message)',
                    ]);
                    return false;
                }

                // 403 = auth ou Host not allowed
                if ($response->status() === 403) {
                    Log::error('[SMS] 403 — Auth incorrecte ou Host bloqué', [
                        'body' => $response->body(),
                        'tip'  => '"Host not in allowlist" = activez Cloud server dans l\'app Android',
                    ]);
                    return false;
                }

                // 401 = mauvais identifiants
                if ($response->status() === 401) {
                    Log::error('[SMS] 401 — Identifiants incorrects', [
                        'user' => $this->user,
                        'tip'  => 'Vérifiez SMS_GATEWAY_USER / SMS_GATEWAY_PASS dans .env',
                    ]);
                    return false;
                }

                Log::warning("[SMS] ❌ Échec tentative {$attempt}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::warning("[SMS] Exception tentative {$attempt} : " . $e->getMessage());
                if ($attempt < $maxRetries) sleep(2);
            }
        }

        Log::error("[SMS] ❌ Impossible d'envoyer à {$normalized}", ['endpoint' => $endpoint]);
        return false;
    }

    // ─── Envoyer à plusieurs destinataires ───────────────────────────────────
    public function sendBulk(array $recipients): bool
    {
        if (!$this->enabled || empty($recipients)) return false;

        $grouped = [];
        foreach ($recipients as $r) {
            $phone = $this->normalizePhone($r['phone'] ?? '');
            if (!$phone) continue;
            $grouped[$this->truncate($r['message'] ?? '')][] = $phone;
        }

        $allOk = true;
        foreach ($grouped as $message => $phones) {
            try {
                $response = Http::timeout(30)
                    ->withBasicAuth($this->user, $this->pass)
                    ->post($this->messageEndpoint(), [
                        'message'      => $message,
                        'phoneNumbers' => $phones,
                    ]);

                if (!$response->successful()) {
                    Log::warning('[SMS] Bulk partiel échoué', [
                        'phones' => $phones,
                        'status' => $response->status(),
                        'body'   => $response->body(),
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

    public function notifyRdvPending(\App\Models\RendezVous $rdv): void
    {
        $date    = $this->formatDate($rdv->date);
        $heure   = $this->formatTime($rdv->start_time);
        $service = $rdv->service?->name    ?? 'votre service';
        $ent     = $rdv->entreprise?->name ?? '';

        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $this->sendMessage(
                $rdv->prestataire->phone,
                "CarEasy - Nouvelle demande RDV\nClient : {$rdv->client?->name}\nService : {$service}\nDate : {$date} a {$heure}\nConnectez-vous pour confirmer."
            );
        }

        if ($rdv->client && !empty($rdv->client->phone)) {
            $this->sendMessage(
                $rdv->client->phone,
                "CarEasy - Demande envoyee\nService : {$service}" . ($ent ? " - {$ent}" : '') . "\nDate : {$date} a {$heure}\nConfirmation a venir."
            );
        }
    }

    public function notifyRdvConfirmed(\App\Models\RendezVous $rdv): void
    {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy - RDV confirme !\nService : {$rdv->service?->name}\nDate : {$this->formatDate($rdv->date)} a {$this->formatTime($rdv->start_time)}\nSoyez ponctuel !"
        );
    }

    public function notifyRdvCancelled(\App\Models\RendezVous $rdv, int $cancelledById): void
    {
        $notifyUser = $cancelledById === $rdv->client_id ? $rdv->prestataire : $rdv->client;
        if (!$notifyUser || empty($notifyUser->phone)) return;

        $raison = $rdv->prestataire_notes ? "\nMotif : {$rdv->prestataire_notes}" : '';
        $this->sendMessage(
            $notifyUser->phone,
            "CarEasy - RDV annule\nService : {$rdv->service?->name}\nDate : {$this->formatDate($rdv->date)}{$raison}\nReprenez RDV sur CarEasy."
        );
    }

    public function notifyRdvCompleted(\App\Models\RendezVous $rdv): void
    {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy - Service termine !\nMerci d'avoir utilise : {$rdv->service?->name}\nLaissez un avis sur CarEasy !"
        );
    }

    public function notifyRdvReminder(\App\Models\RendezVous $rdv): void
    {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $ent = $rdv->entreprise?->name ?? '';
        $this->sendMessage(
            $rdv->client->phone,
            "CarEasy - Rappel RDV demain\nService : {$rdv->service?->name}" . ($ent ? " - {$ent}" : '') . "\nDate : {$this->formatDate($rdv->date)} a {$this->formatTime($rdv->start_time)}\nN'oubliez pas !"
        );
    }

    public function notifyRegistration(string $phone, string $name): void
    {
        $firstName = explode(' ', $name)[0];
        $this->sendMessage($phone, "CarEasy - Bienvenue {$firstName} !\nVotre compte est cree. Trouvez des prestataires auto pres de chez vous.");
    }

    public function notifyEntrepriseApproved(\App\Models\Entreprise $entreprise): void{
        $user = $entreprise->prestataire;
        if (!$user || empty($user->phone)) return;

        $this->sendMessage(
            $user->phone,
            "CarEasy - Felicitations !\nVotre entreprise \"{$entreprise->name}\" est validee.\nPeriode d'essai 30j activee. Connectez-vous !"
        );
    }

    public function notifyEntrepriseRejected(\App\Models\Entreprise $entreprise, ?string $reason = null): void {
        $user = $entreprise->prestataire;
        if (!$user || empty($user->phone)) return;

        $msg = "CarEasy - Demande refusee\nEntreprise : {$entreprise->name}\n";
        if ($reason) $msg .= "Motif : " . $this->truncate($reason, 80) . "\n";
        $msg .= "Corrigez et soumettez a nouveau.";

        $this->sendMessage($user->phone, $msg);
    }

    public function sendOtp(string $phone, string $code, string $name = ''): bool
    {
        $greeting = $name ? "Bonjour " . explode(' ', $name)[0] . ", " : '';
        return $this->sendMessage(
            $phone,
            "CarEasy - {$greeting}votre code : {$code}\nValide 5 min. Ne le partagez jamais."
        );
    }

    public function sendReminderForTomorrow(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $rdvs     = \App\Models\RendezVous::with(['client', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();

        foreach ($rdvs as $rdv) {
            $this->notifyRdvReminder($rdv);
            usleep(500_000);
        }

        Log::info('[SMS] Rappels J-1 envoyés', ['count' => $rdvs->count()]);
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function normalizePhone(string $phone): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        if (empty($clean)) return null;

        if (preg_match('/^\d{8}$/', $clean))    return '+229' . $clean;
        if (preg_match('/^229\d{8}$/', $clean)) return '+' . $clean;
        if (str_starts_with($clean, '+'))        return $clean;

        return '+' . $clean;
    }

    private function truncate(string $text, int $max = 160): string{
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max - 3) . '...';
    }

    private function formatDate($date): string  {
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