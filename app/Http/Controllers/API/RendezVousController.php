<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Notifications\RdvNotification; // ← AJOUT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RendezVousController extends Controller{
    public function getAvailableSlots($serviceId, $date) {
        try {
            $service = Service::with('entreprise')->findOrFail($serviceId);
            if (!$service->entreprise || $service->entreprise->status !== 'validated') {
                return response()->json(['message' => 'Service non disponible'], 403);
            }
            $availableSlots = RendezVous::getAvailableSlots($service, $date);
            return response()->json(['date' => $date, 'service_id' => $serviceId, 'slots' => $availableSlots]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération créneaux:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la récupération des créneaux'], 500);
        }
    }

    public function store(Request $request) {
        $user = Auth::user();

        if (empty($user->phone)) {
            $validator = Validator::make($request->all(), [
                'phone' => [
                    'required',
                    'regex:/^[0-9+\s\-]+$/',
                    \Illuminate\Validation\Rule::unique('users', 'phone')
                        ->ignore($user->id),  
                ]
            ], [
                'phone.unique' => 'Ce numéro est déjà utilisé par un autre compte.',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            $user->update(['phone' => $request->phone]);
        }

        $validator = Validator::make($request->all(), [
            'service_id'   => 'required|exists:services,id',
            'date'         => 'required|date|after_or_equal:today',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'client_notes' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $service = Service::with('entreprise', 'prestataire')->findOrFail($request->service_id);

            if (!$service->entreprise || $service->entreprise->status !== 'validated') {
                return response()->json(['message' => 'Ce service n\'est pas disponible pour le moment'], 403);
            }

            $dayOfWeek = strtolower(Carbon::parse($request->date)->locale('en')->dayName);
            if (!$service->is_always_open) {
                if (!isset($service->schedule[$dayOfWeek]) || !$service->schedule[$dayOfWeek]['is_open']) {
                    return response()->json(['message' => 'Le service est fermé ce jour'], 422);
                }
                $schedule = $service->schedule[$dayOfWeek];
                if ($request->start_time < $schedule['start'] || $request->end_time > $schedule['end']) {
                    return response()->json(['message' => 'Le créneau demandé est en dehors des heures d\'ouverture'], 422);
                }
            }

            if (!RendezVous::isTimeSlotAvailable($service->id, $request->date, $request->start_time, $request->end_time)) {
                return response()->json(['message' => 'Ce créneau n\'est plus disponible'], 422);
            }

            $rendezVous = RendezVous::create([
                'service_id'     => $service->id,
                'client_id'      => $user->id,
                'prestataire_id' => $service->prestataire_id,
                'entreprise_id'  => $service->entreprise_id,
                'date'           => $request->date,
                'start_time'     => $request->start_time,
                'end_time'       => $request->end_time,
                'client_notes'   => $request->client_notes,
                'status'         => RendezVous::STATUS_PENDING,
            ]);

            $rendezVous->load(['service', 'client', 'prestataire', 'entreprise']);

            
            try {
                $prestataire = User::find($service->prestataire_id);
                if ($prestataire) {
                    $prestataire->notify(new RdvNotification($rendezVous, 'pending'));
                }
            } catch (\Exception $e) {
                Log::warning('Notification RDV pending échouée:', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'message'    => 'Demande de rendez-vous envoyée avec succès',
                'rendez_vous' => $rendezVous,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création rendez-vous:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du rendez-vous'], 500);
        }
    }

    public function index() {
        $user = Auth::user();

        $rendezVous = RendezVous::with([
                'service', 'entreprise', 'service.domaine', 'client', 'prestataire'
            ])
            ->where(function ($query) use ($user) {
                // Récupérer TOUS les RDV où l'utilisateur est impliqué,
                // que ce soit comme prestataire OU comme client.
                // Un prestataire peut aussi passer des commandes à d'autres prestataires.
                $query->where('prestataire_id', $user->id)
                    ->orWhere('client_id', $user->id);
            })
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json($rendezVous);
    }

    public function show($id) {
        $rendezVous = RendezVous::with(['service', 'client', 'prestataire', 'entreprise', 'service.domaine', 'review'])->findOrFail($id);
        return response()->json($rendezVous);
    }

    public function confirm($id)
    {
        $user       = Auth::user();
        $rendezVous = RendezVous::with(['client', 'service', 'prestataire'])->findOrFail($id);

        if ($rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        if (!$rendezVous->isPending()) {
            return response()->json(['message' => 'Ce rendez-vous ne peut pas être confirmé'], 422);
        }

        $rendezVous->update([
            'status'       => RendezVous::STATUS_CONFIRMED,
            'confirmed_at' => Carbon::now(),
        ]);

        
        try {
            $client = User::find($rendezVous->client_id);
            if ($client) {
                $client->notify(new RdvNotification($rendezVous, 'confirmed'));
            }
        } catch (\Exception $e) {
            Log::warning('Notification RDV confirmed échouée:', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message'     => 'Rendez-vous confirmé avec succès',
            'rendez_vous' => $rendezVous,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user       = Auth::user();
        $rendezVous = RendezVous::with(['client', 'prestataire', 'service'])->findOrFail($id);

        if ($rendezVous->client_id !== $user->id && $rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        if (!$rendezVous->canBeCancelled()) {
            return response()->json(['message' => 'Ce rendez-vous ne peut pas être annulé'], 422);
        }

        $validator = Validator::make($request->all(), ['reason' => 'nullable|string|max:255']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rendezVous->update([
            'status'             => RendezVous::STATUS_CANCELLED,
            'cancelled_at'       => Carbon::now(),
            'prestataire_notes'  => $request->reason,
        ]);

        try {
            $notifyUserId = $rendezVous->client_id === $user->id
                ? $rendezVous->prestataire_id
                : $rendezVous->client_id;

            $notifyUser = User::find($notifyUserId);
            if ($notifyUser) {
                $notifyUser->notify(new RdvNotification($rendezVous, 'cancelled'));
            }
        } catch (\Exception $e) {
            Log::warning('Notification RDV cancelled échouée:', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message'     => 'Rendez-vous annulé avec succès',
            'rendez_vous' => $rendezVous,
        ]);
    }

    public function complete($id)
    {
        $user       = Auth::user();
        $rendezVous = RendezVous::with(['client', 'service'])->findOrFail($id);

        if ($rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        if (!$rendezVous->isConfirmed()) {
            return response()->json(['message' => 'Seuls les rendez-vous confirmés peuvent être marqués comme terminés'], 422);
        }

        $rendezVous->update([
            'status'       => RendezVous::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);

        
        try {
            $client = User::find($rendezVous->client_id);
            if ($client) {
                $client->notify(new RdvNotification($rendezVous, 'completed'));
            }
        } catch (\Exception $e) {
            Log::warning('Notification RDV completed échouée:', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message'     => 'Rendez-vous marqué comme terminé',
            'rendez_vous' => $rendezVous,
        ]);
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'start'      => 'required|date',
            'end'        => 'required|date|after:start',
            'service_id' => 'nullable|exists:services,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $query = RendezVous::with(['client', 'service', 'entreprise'])
                ->whereIn('status', [RendezVous::STATUS_PENDING, RendezVous::STATUS_CONFIRMED])
                ->whereBetween('date', [
                    Carbon::parse($request->start)->format('Y-m-d'),
                    Carbon::parse($request->end)->format('Y-m-d'),
                ]);

            if ($user->isPrestataire()) {
                $query->where('prestataire_id', $user->id);
            } else {
                $query->where('client_id', $user->id);
            }

            if ($request->service_id) {
                $query->where('service_id', $request->service_id);
            }

            $rendezVous = $query->get();
            $events     = [];

            foreach ($rendezVous as $rdv) {
                $color  = match ($rdv->status) {
                    RendezVous::STATUS_CONFIRMED  => '#10b981',
                    RendezVous::STATUS_CANCELLED  => '#ef4444',
                    RendezVous::STATUS_COMPLETED  => '#6b7280',
                    default                       => '#f59e0b',
                };
                $events[] = [
                    'id'              => (string) $rdv->id,
                    'title'           => $rdv->service->name,
                    'start'           => $rdv->date . 'T' . $rdv->start_time,
                    'end'             => $rdv->date . 'T' . $rdv->end_time,
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'allDay'          => false,
                    'extendedProps'   => [
                        'status'      => $rdv->status,
                        'client'      => ['id' => $rdv->client->id, 'name' => $rdv->client->name, 'phone' => $rdv->client->phone ?? null],
                        'service'     => ['id' => $rdv->service->id, 'name' => $rdv->service->name],
                        'entreprise'  => ['id' => $rdv->entreprise->id, 'name' => $rdv->entreprise->name],
                        'notes'       => $rdv->client_notes,
                    ],
                ];
            }

            return response()->json($events);

        } catch (\Exception $e) {
            Log::error('Erreur calendrier:', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la récupération des événements'], 500);
        }
    }
}