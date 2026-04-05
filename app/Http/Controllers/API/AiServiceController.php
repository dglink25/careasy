<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiServiceController extends Controller {

    public function nearby(Request $request) {
        $lat    = (float) $request->input('lat', 0);
        $lng    = (float) $request->input('lng', 0);
        $radius = (float) $request->input('radius', 10);
        $domaine = $request->input('domaine');
        $limit  = (int) $request->input('limit', 8);

        if (!$lat || !$lng) {
            return response()->json(['message' => 'lat et lng requis'], 400);
        }
        
        $haversineWhere = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(entreprises.latitude))
            * cos(radians(entreprises.longitude) - radians({$lng}))
            + sin(radians({$lat})) * sin(radians(entreprises.latitude))
        ))";

        $query = Service::with(['entreprise', 'domaine'])
            ->join('entreprises', 'services.entreprise_id', '=', 'entreprises.id')
            ->join('domaines', 'services.domaine_id', '=', 'domaines.id')
            ->where('entreprises.status', 'validated')
            ->whereNotNull('entreprises.latitude')
            ->whereNotNull('entreprises.longitude')
            // BUG FIX #10a : ne montrer que les services visibles
            ->where(function($q) {
                $q->whereNull('services.is_visibility')
                  ->orWhere('services.is_visibility', true);
            })
            ->selectRaw("services.*, {$haversineWhere} AS distance_km")
            ->whereRaw("{$haversineWhere} <= ?", [$radius])
            ->orderByRaw("{$haversineWhere} ASC");

        if ($domaine) {
            $query->where('domaines.name', 'like', "%{$domaine}%");
        }

        $services = $query->limit($limit)->get();

        // BUG FIX #10b : si aucun résultat avec le rayon demandé,
        // retenter avec rayon x3 automatiquement
        if ($services->isEmpty() && $radius < 50) {
            $bigRadius = min($radius * 3, 100);
            $query2 = Service::with(['entreprise', 'domaine'])
                ->join('entreprises', 'services.entreprise_id', '=', 'entreprises.id')
                ->join('domaines', 'services.domaine_id', '=', 'domaines.id')
                ->where('entreprises.status', 'validated')
                ->whereNotNull('entreprises.latitude')
                ->whereNotNull('entreprises.longitude')
                ->where(function($q) {
                    $q->whereNull('services.is_visibility')
                      ->orWhere('services.is_visibility', true);
                })
                ->selectRaw("services.*, {$haversineWhere} AS distance_km")
                ->whereRaw("{$haversineWhere} <= ?", [$bigRadius])
                ->orderByRaw("{$haversineWhere} ASC");

            if ($domaine) {
                $query2->where('domaines.name', 'like', "%{$domaine}%");
            }
            $services = $query2->limit($limit)->get();
        }

        $result = $services->map(function ($service) {
            return [
                'id'           => $service->id,
                'name'         => $service->name,
                'start_time'   => $service->start_time,
                'end_time'     => $service->end_time,
                'is_open_24h'  => (bool) $service->is_open_24h,
                'is_always_open' => (bool) $service->is_always_open,
                'price'        => $service->price,
                'price_promo'  => $service->price_promo,
                'has_promo'    => (bool) $service->has_promo,
                'is_price_on_request' => (bool) $service->is_price_on_request,
                'descriptions' => $service->descriptions,
                'distance_km'  => round($service->distance_km, 1),
                // BUG FIX #10c : retourner domaine comme string, pas comme objet
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


    public function index(Request $request)
    {
        $query = Service::with(['entreprise', 'domaine'])
            ->whereHas('entreprise', fn($q) => $q->where('status', 'validated'))
            // BUG FIX #11a : ne montrer que les services visibles
            ->where(function($q) {
                $q->whereNull('is_visibility')
                  ->orWhere('is_visibility', true);
            });

        if ($request->filled('domaine')) {
            $query->whereHas('domaine', fn($q) =>
                $q->where('name', 'like', "%{$request->domaine}%")
            );
        }

        $services = $query->limit((int) $request->input('limit', 20))->get();

        // BUG FIX #11b : formater la réponse de la même façon que nearby
        // pour que _normalize_service() dans main.py fonctionne correctement
        $result = $services->map(function ($service) {
            return [
                'id'           => $service->id,
                'name'         => $service->name,
                'start_time'   => $service->start_time,
                'end_time'     => $service->end_time,
                'is_open_24h'  => (bool) $service->is_open_24h,
                'is_always_open' => (bool) $service->is_always_open,
                'price'        => $service->price,
                'price_promo'  => $service->price_promo,
                'has_promo'    => (bool) $service->has_promo,
                'is_price_on_request' => (bool) $service->is_price_on_request,
                'descriptions' => $service->descriptions,
                'distance_km'  => null, // pas de GPS dans cette route
                // Retourner domaine comme string
                'domaine'      => $service->domaine?->name,
                'entreprise'   => [
                    'id'                      => $service->entreprise?->id,
                    'name'                    => $service->entreprise?->name,
                    'latitude'                => $service->entreprise?->latitude,
                    'longitude'               => $service->entreprise?->longitude,
                    'google_formatted_address'=> $service->entreprise?->google_formatted_address,
                    'call_phone'              => $service->entreprise?->call_phone,
                    'whatsapp_phone'          => $service->entreprise?->whatsapp_phone,
                    'status_online'           => (bool) ($service->entreprise?->status_online ?? false),
                    'logo'                    => $service->entreprise?->logo,
                ],
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function domaines()
    {
        return response()->json([
            'data' => Domaine::orderBy('name')->get(['id', 'name'])
        ]);
    }
}