<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class WhatsAppService{
    protected string $baseUrl;
    protected string $secret;
    protected int    $timeout;

    public function __construct() {
        $this->baseUrl = config('whatsapp.gateway_url', 'http://localhost:3001');
        $this->secret  = config('whatsapp.api_secret', '35abced573ff026656aaf2e6e1dec87a22b1ea51c3cc27417c5b0fad7b54a67b');
        $this->timeout = 10;
    }

   
    private function headers(): array{
        return [
            'Content-Type'  => 'application/json',
            'X-Api-Secret'  => $this->secret,
        ];
    }

    // ─── Vérifier si le service est disponible ────────────────────────────────
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/status");
            return $response->ok() && $response->json('ready') === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── Envoyer un message simple ────────────────────────────────────────────
    public function sendMessage(string $phone, string $message): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/send", [
                    'phone'   => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp envoyé', ['to' => $phone]);
                return true;
            }

            Log::warning('WhatsApp échec', [
                'to'     => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp erreur', ['to' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ─── Envoyer à plusieurs destinataires ───────────────────────────────────
    public function sendBulk(array $recipients): array
    {
        // $recipients = [['phone' => '...', 'message' => '...'], ...]
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/send-bulk", [
                    'recipients' => $recipients,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('WhatsApp bulk échec', ['status' => $response->status()]);
            return ['success' => false, 'sent' => 0];

        } catch (\Exception $e) {
            Log::error('WhatsApp bulk erreur', ['error' => $e->getMessage()]);
            return ['success' => false, 'sent' => 0];
        }
    }

    // ─── Envoyer notification RDV complète ───────────────────────────────────
    public function sendRdvNotification(
        array  $rdvData,
        string $event,
        array  $recipients
    ): bool {
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
            Log::error('WhatsApp RDV notif erreur', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─── Méthodes utilitaires pour les événements RDV ─────────────────────────

    /**
     * RDV créé (en attente) — notifie le prestataire et le client
     */
    public function notifyRdvPending(\App\Models\RendezVous $rdv): void
    {
        $rdvData    = $this->buildRdvData($rdv);
        $recipients = [];

        // Notifier le prestataire
        if ($rdv->prestataire && !empty($rdv->prestataire->phone)) {
            $recipients[] = [
                'phone' => $rdv->prestataire->phone,
                'name'  => $rdv->prestataire->name,
                'role'  => 'prestataire',
            ];
        }

        // Notifier le client (confirmation d'envoi)
        if ($rdv->client && !empty($rdv->client->phone)) {
            $recipients[] = [
                'phone' => $rdv->client->phone,
                'name'  => $rdv->client->name,
                'role'  => 'client',
            ];
        }

        if (!empty($recipients)) {
            $this->sendRdvNotification($rdvData, 'pending', $recipients);
        }
    }

    /**
     * RDV confirmé — notifie le client
     */
    public function notifyRdvConfirmed(\App\Models\RendezVous $rdv): void
    {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'confirmed',
            [[
                'phone' => $rdv->client->phone,
                'name'  => $rdv->client->name,
                'role'  => 'client',
            ]]
        );
    }

    /**
     * RDV annulé — notifie l'autre partie
     */
    public function notifyRdvCancelled(\App\Models\RendezVous $rdv, int $cancelledById): void
    {
        // Notifier la partie qui n'a pas annulé
        $notifyUser = $cancelledById === $rdv->client_id
            ? $rdv->prestataire
            : $rdv->client;

        $role = $cancelledById === $rdv->client_id ? 'prestataire' : 'client';

        if (!$notifyUser || empty($notifyUser->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'cancelled',
            [[
                'phone' => $notifyUser->phone,
                'name'  => $notifyUser->name,
                'role'  => $role,
            ]]
        );
    }

    /**
     * RDV terminé — notifie le client
     */
    public function notifyRdvCompleted(\App\Models\RendezVous $rdv): void
    {
        if (!$rdv->client || empty($rdv->client->phone)) return;

        $this->sendRdvNotification(
            $this->buildRdvData($rdv),
            'completed',
            [[
                'phone' => $rdv->client->phone,
                'name'  => $rdv->client->name,
                'role'  => 'client',
            ]]
        );
    }

    /**
     * Rappel RDV J-1 (appelé par le scheduler Laravel)
     */
    public function sendReminderForTomorrow(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $rdvs = \App\Models\RendezVous::with(['client', 'prestataire', 'service', 'entreprise'])
            ->where('date', $tomorrow)
            ->where('status', \App\Models\RendezVous::STATUS_CONFIRMED)
            ->get();

        foreach ($rdvs as $rdv) {
            $recipients = [];

            if ($rdv->client && !empty($rdv->client->phone)) {
                $recipients[] = [
                    'phone' => $rdv->client->phone,
                    'name'  => $rdv->client->name,
                    'role'  => 'client',
                ];
            }

            if (!empty($recipients)) {
                $this->sendRdvNotification($this->buildRdvData($rdv), 'reminder', $recipients);
            }

            // Pause pour éviter le spam
            usleep(500000); // 0.5 secondes
        }

        Log::info("WhatsApp rappels J-1 envoyés", ['count' => $rdvs->count(), 'date' => $tomorrow]);
    }

    // ─── Construire les données RDV pour le gateway ───────────────────────────
    private function buildRdvData(\App\Models\RendezVous $rdv): array
    {
        return [
            'id'               => $rdv->id,
            'date'             => $rdv->date,
            'start_time'       => $rdv->start_time ? substr($rdv->start_time, 0, 5) : '',
            'end_time'         => $rdv->end_time   ? substr($rdv->end_time, 0, 5)   : '',
            'service_name'     => $rdv->service?->name    ?? 'Service',
            'entreprise_name'  => $rdv->entreprise?->name ?? 'Entreprise',
            'client_name'      => $rdv->client?->name     ?? 'Client',
            'client_notes'     => $rdv->client_notes      ?? '',
            'cancel_reason'    => $rdv->prestataire_notes ?? '',
        ];
    }
}