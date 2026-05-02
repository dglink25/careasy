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
    public function sendMessage(string $phone, string $message): bool  {
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers())
                    ->post("{$this->baseUrl}/send", [
                        'phone'   => $phone,
                        'message' => $message,
                    ]);

                if ($response->successful() && $response->json('success')) {
                    Log::info("WhatsApp envoyé à {$phone} (tentative {$attempt})");
                    return true;
                }

                // Si "non connecté", attendre 2 secondes et réessayer
                $errorMsg = $response->json('message') ?? '';
                if (str_contains($errorMsg, 'non connecté') && $attempt < $maxRetries) {
                    Log::warning("WhatsApp non connecté, retry {$attempt}/{$maxRetries}...");
                    sleep(2);
                    continue;
                }

                Log::warning("WhatsApp échec à {$phone}", [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'attempt' => $attempt,
                ]);

            } catch (\Exception $e) {
                Log::warning("WhatsApp erreur tentative {$attempt}: {$e->getMessage()}");
                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            }
        }

        Log::error("WhatsApp: impossible d'envoyer à {$phone} après {$maxRetries} tentatives");
        return false;
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

    // ─── RDV créé → notifie prestataire + client ──────────────────────────────
    public function notifyRdvPending(\App\Models\RendezVous $rdv): void {
        $recipients = [];

        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $recipients[] = ['phone' => $rdv->prestataire->phone, 'name' => $rdv->prestataire->name, 'role' => 'prestataire'];
        }
        if ($rdv->client && !empty($rdv->client->phone)) {
            $recipients[] = ['phone' => $rdv->client->phone, 'name' => $rdv->client->name, 'role' => 'client'];
        }

        if (!empty($recipients)) {
            $this->sendRdvNotification($this->buildRdvData($rdv), 'pending', $recipients);
        }
    }

    // ─── RDV confirmé → notifie client ───────────────────────────────────────
    public function notifyRdvConfirmed(\App\Models\RendezVous $rdv): void {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'confirmed',
            [['phone' => $rdv->client->phone, 'name' => $rdv->client->name, 'role' => 'client']]
        );
    }

    // ─── RDV annulé → notifie l'autre partie ─────────────────────────────────
    public function notifyRdvCancelled(\App\Models\RendezVous $rdv, int $cancelledById): void {
        $notifyUser = $cancelledById === $rdv->client_id ? $rdv->prestataire : $rdv->client;
        $role       = $cancelledById === $rdv->client_id ? 'prestataire' : 'client';

        if (!$notifyUser || empty($notifyUser->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'cancelled',
            [['phone' => $notifyUser->phone, 'name' => $notifyUser->name, 'role' => $role]]
        );
    }

    // ─── RDV terminé → notifie client ────────────────────────────────────────
    public function notifyRdvCompleted(\App\Models\RendezVous $rdv): void  {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'completed',
            [['phone' => $rdv->client->phone, 'name' => $rdv->client->name, 'role' => 'client']]
        );
    }

    // ─── Rappels J-1 (appelé par le scheduler) ───────────────────────────────
    public function sendReminderForTomorrow(): void {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $rdvs = \App\Models\RendezVous::with(['client', 'prestataire', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();

        foreach ($rdvs as $rdv) {
            if ($rdv->client && !empty($rdv->client->phone)) {
                $this->sendRdvNotification(
                    $this->buildRdvData($rdv),
                    'reminder',
                    [['phone' => $rdv->client->phone, 'name' => $rdv->client->name, 'role' => 'client']]
                );
                usleep(500000);
            }
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
}