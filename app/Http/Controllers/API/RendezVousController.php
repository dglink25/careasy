<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RendezVousController extends Controller{
    public function getAvailableSlots($serviceId, $date) {
        try {
            $service = Service::with('entreprise')->findOrFail($serviceId);
            
            // Vérifier si le service est disponible
            if (!$service->entreprise || $service->entreprise->status !== 'validated') {
                return response()->json([
                    'message' => 'Service non disponible'
                ], 403);
            }

            $availableSlots = RendezVous::getAvailableSlots($service, $date);
            
            return response()->json([
                'date' => $date,
                'service_id' => $serviceId,
                'slots' => $availableSlots
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération créneaux:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la récupération des créneaux'
            ], 500);
        }
    }


    public function store(Request $request) {
        $user = Auth::user();

        if (empty($user->phone)) {

            $validator = Validator::make($request->all(), [
                'phone' => 'required|regex:/^[0-9+\s\-]+$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'phone' => $request->phone
            ]);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'client_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $service = Service::with('entreprise', 'prestataire')->findOrFail($request->service_id);

            // Vérifier que le service est disponible
            if (!$service->entreprise || $service->entreprise->status !== 'validated') {
                return response()->json([
                    'message' => 'Ce service n\'est pas disponible pour le moment'
                ], 403);
            }

            // Vérifier que le créneau est dans les horaires d'ouverture
            $dayOfWeek = strtolower(Carbon::parse($request->date)->locale('en')->dayName);
            
            if (!$service->is_always_open) {
                if (!isset($service->schedule[$dayOfWeek]) || !$service->schedule[$dayOfWeek]['is_open']) {
                    return response()->json([
                        'message' => 'Le service est fermé ce jour'
                    ], 422);
                }

                $schedule = $service->schedule[$dayOfWeek];
                if ($request->start_time < $schedule['start'] || $request->end_time > $schedule['end']) {
                    return response()->json([
                        'message' => 'Le créneau demandé est en dehors des heures d\'ouverture'
                    ], 422);
                }
            }

            // Vérifier la disponibilité du créneau
            if (!RendezVous::isTimeSlotAvailable(
                $service->id, 
                $request->date, 
                $request->start_time, 
                $request->end_time
            )) {
                return response()->json([
                    'message' => 'Ce créneau n\'est plus disponible'
                ], 422);
            }

            $rendezVous = RendezVous::create([
                'service_id' => $service->id,
                'client_id' => $user->id,
                'prestataire_id' => $service->prestataire_id,
                'entreprise_id' => $service->entreprise_id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'client_notes' => $request->client_notes,
                'status' => RendezVous::STATUS_PENDING
            ]);

            // Charger les relations
            $rendezVous->load(['service', 'client', 'prestataire', 'entreprise']);

            // TODO: Envoyer notification SMS au prestataire
            $this->sendSmsNotification(
                $rendezVous->prestataire->phone,
                "Nouvelle demande de rendez-vous pour {$service->name} le " . 
                Carbon::parse($request->date)->format('d/m/Y') . " à {$request->start_time}"
            );

            return response()->json([
                'message' => 'Demande de rendez-vous envoyée avec succès',
                'rendez_vous' => $rendezVous
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création rendez-vous:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création du rendez-vous'
            ], 500);
        }
    }

   public function index() {
    $user = Auth::user();
    
    Log::info('DEBUG index rendez-vous', [
        'user_id'        => $user->id,
        'user_role'      => $user->role,
        'isPrestataire'  => $user->isPrestataire(),
    ]);
    
    $query = RendezVous::with(['service', 'entreprise', 'service.domaine', 'client', 'prestataire']);

    if ($user->isPrestataire()) {
        $query->where('prestataire_id', $user->id);
    } else {
        $query->where('client_id', $user->id);
    }

    $rendezVous = $query->orderBy('date', 'desc')
                        ->orderBy('start_time', 'desc')
                        ->get();

    Log::info('DEBUG résultats', [
        'count'      => $rendezVous->count(),
        'client_ids' => $rendezVous->pluck('client_id'),
        'statuses'   => $rendezVous->pluck('status'),
    ]);

    return response()->json($rendezVous);
}
    public function show($id) {
        $user = Auth::user();
        
        $rendezVous = RendezVous::with(['service', 'client', 'prestataire', 'entreprise', 'service.domaine'])
                                ->findOrFail($id);

        return response()->json($rendezVous);
    }


    public function confirm($id)  {
        $user = Auth::user();
        
        $rendezVous = RendezVous::with(['client', 'service'])->findOrFail($id);

        // Vérifier que c'est le prestataire
        if ($rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$rendezVous->isPending()) {
            return response()->json([
                'message' => 'Ce rendez-vous ne peut pas être confirmé'
            ], 422);
        }

        $rendezVous->update([
            'status' => RendezVous::STATUS_CONFIRMED,
            'confirmed_at' => Carbon::now()
        ]);

        // TODO: Envoyer SMS de confirmation au client
        $this->sendSmsNotification(
            $rendezVous->client->phone,
            "Votre rendez-vous pour {$rendezVous->service->name} le " .
            Carbon::parse($rendezVous->date)->format('d/m/Y') . " à {$rendezVous->start_time} a été confirmé."
        );

        return response()->json([
            'message' => 'Rendez-vous confirmé avec succès',
            'rendez_vous' => $rendezVous
        ]);
    }


    public function cancel(Request $request, $id)   {
        $user = Auth::user();
        
        $rendezVous = RendezVous::with(['client', 'prestataire', 'service'])->findOrFail($id);

        // Vérifier les permissions
        if ($rendezVous->client_id !== $user->id && 
            $rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$rendezVous->canBeCancelled()) {
            return response()->json([
                'message' => 'Ce rendez-vous ne peut pas être annulé'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rendezVous->update([
            'status' => RendezVous::STATUS_CANCELLED,
            'cancelled_at' => Carbon::now(),
            'prestataire_notes' => $request->reason
        ]);

        // Notifier l'autre partie
        $notifyUser = $rendezVous->client_id === $user->id 
            ? $rendezVous->prestataire 
            : $rendezVous->client;

        // TODO: Envoyer SMS d'annulation
        $this->sendSmsNotification(
            $notifyUser->phone,
            "Le rendez-vous pour {$rendezVous->service->name} du " .
            Carbon::parse($rendezVous->date)->format('d/m/Y') . " a été annulé."
        );

        return response()->json([
            'message' => 'Rendez-vous annulé avec succès',
            'rendez_vous' => $rendezVous
        ]);
    }


    public function complete($id)  {
        $user = Auth::user();
        
        $rendezVous = RendezVous::findOrFail($id);

        if ($rendezVous->prestataire_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$rendezVous->isConfirmed()) {
            return response()->json([
                'message' => 'Seuls les rendez-vous confirmés peuvent être marqués comme terminés'
            ], 422);
        }

        $rendezVous->update([
            'status' => RendezVous::STATUS_COMPLETED,
            'completed_at' => Carbon::now()
        ]);

        return response()->json([
            'message' => 'Rendez-vous marqué comme terminé',
            'rendez_vous' => $rendezVous
        ]);
    }
    
    private function sendSmsNotification($phone, $message) {
        Log::info('SMS à envoyer:', [
            'phone' => $phone,
            'message' => $message
        ]);
    }

    public function calendar(Request $request) {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'service_id' => 'nullable|exists:services,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $query = RendezVous::with(['client', 'service', 'entreprise'])
                ->whereIn('status', [RendezVous::STATUS_PENDING, RendezVous::STATUS_CONFIRMED])
                ->whereBetween('date', [
                    Carbon::parse($request->start)->format('Y-m-d'),
                    Carbon::parse($request->end)->format('Y-m-d')
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

            $events = [];
            
            foreach ($rendezVous as $rdv) {
                // Déterminer la couleur selon le statut
                $color = '#f59e0b'; // pending par défaut
                
                if ($rdv->status === RendezVous::STATUS_CONFIRMED) {
                    $color = '#10b981';
                } elseif ($rdv->status === RendezVous::STATUS_CANCELLED) {
                    $color = '#ef4444';
                } elseif ($rdv->status === RendezVous::STATUS_COMPLETED) {
                    $color = '#6b7280';
                }

                // Construire les dates ISO sans utiliser Carbon::parse deux fois
                $start = $rdv->date . 'T' . $rdv->start_time;
                $end = $rdv->date . 'T' . $rdv->end_time;

                $events[] = [
                    'id' => (string) $rdv->id,
                    'title' => $rdv->service->name,
                    'start' => $start,
                    'end' => $end,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'allDay' => false,
                    'extendedProps' => [
                        'status' => $rdv->status,
                        'client' => [
                            'id' => $rdv->client->id,
                            'name' => $rdv->client->name,
                            'phone' => $rdv->client->phone ?? null
                        ],
                        'service' => [
                            'id' => $rdv->service->id,
                            'name' => $rdv->service->name
                        ],
                        'entreprise' => [
                            'id' => $rdv->entreprise->id,
                            'name' => $rdv->entreprise->name
                        ],
                        'notes' => $rdv->client_notes
                    ]
                ];
            }

            return response()->json($events);

        } catch (\Exception $e) {
            Log::error('Erreur calendrier:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des événements',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}