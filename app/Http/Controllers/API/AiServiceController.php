<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiServiceController extends Controller{

    public function nearby(Request $request) {
        $lat    = (float) $request->input('lat', 0);
        $lng    = (float) $request->input('lng', 0);
        $radius = (float) $request->input('radius', 10);
        $domaine = $request->input('domaine');
        $limit  = (int)   $request->input('limit', 8);

        if (!$lat || !$lng) {
            return response()->json(['message' => 'lat et lng requis'], 400);
        }

        // Formule Haversine en SQL pour filtrer par rayon
        $haversine = "(6371 * acos(
            cos(radians(?)) * cos(radians(entreprises.latitude))
            * cos(radians(entreprises.longitude) - radians(?))
            + sin(radians(?)) * sin(radians(entreprises.latitude))
        ))";

        $query = Service::with(['entreprise', 'domaine'])
            ->join('entreprises', 'services.entreprise_id', '=', 'entreprises.id')
            ->join('domaines',    'services.domaine_id',    '=', 'domaines.id')
            ->where('entreprises.status', 'validated')
            ->whereNotNull('entreprises.latitude')
            ->whereNotNull('entreprises.longitude')
            ->selectRaw("services.*, {$haversine} AS distance_km", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radius)
            ->orderBy('distance_km');

        if ($domaine) {
            $query->where('domaines.name', 'like', "%{$domaine}%");
        }

        $services = $query->limit($limit)->get();

        // Formatter les données pour Python
        $result = $services->map(function ($service) {
            return [
                'id'           => $service->id,
                'name'         => $service->name,
                'start_time'   => $service->start_time,
                'end_time'     => $service->end_time,
                'is_open_24h'  => (bool) $service->is_open_24h,
                'price'        => $service->price,
                'descriptions' => $service->descriptions,
                'distance_km'  => round($service->distance_km, 1),
                'domaine'      => $service->domaine?->name,
                'entreprise'   => [
                    'id'                      => $service->entreprise?->id,
                    'name'                    => $service->entreprise?->name,
                    'latitude'                => $service->entreprise?->latitude,
                    'longitude'               => $service->entreprise?->longitude,
                    'google_formatted_address'=> $service->entreprise?->google_formatted_address,
                    'call_phone'              => $service->entreprise?->call_phone,
                    'whatsapp_phone'          => $service->entreprise?->whatsapp_phone,
                    'status_online'           => (bool) $service->entreprise?->status_online,
                    'logo'                    => $service->entreprise?->logo,
                ],
            ];
        });

        return response()->json(['data' => $result, 'count' => $result->count()]);
    }

    /**
     * GET /api/ai/services
     * Liste tous les services avec filtres optionnels.
     */
    public function index(Request $request)
    {
        $query = Service::with(['entreprise', 'domaine'])
            ->whereHas('entreprise', fn($q) => $q->where('status', 'validated'));

        if ($request->filled('domaine')) {
            $query->whereHas('domaine', fn($q) =>
                $q->where('name', 'like', "%{$request->domaine}%")
            );
        }

        $services = $query->limit((int) $request->input('limit', 20))->get();
        return response()->json(['data' => $services]);
    }

    /**
     * GET /api/ai/domaines
     * Retourne la liste de tous les domaines.
     */
    public function domaines()
    {
        return response()->json([
            'data' => Domaine::orderBy('name')->get(['id', 'name'])
        ]);
    }
}
