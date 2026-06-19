<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Domaine;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiServiceController extends Controller {

    // ─── Sous-requête abonnement payant actif (même logique que ServiceController) ───
    private function abonnementPayantSub()  {
        return Abonnement::select([
                'abonnements.entreprise_id',
                'plans.price as plan_price',
            ])
            ->leftJoin('plans', 'abonnements.plan_id', '=', 'plans.id')
            ->where('abonnements.statut', 'actif')
            ->where('abonnements.date_fin', '>', now())
            ->whereNull('abonnements.deleted_at')
            ->whereNotNull('abonnements.plan_id'); // exclut les essais gratuits
    }

    public function nearby(Request $request) {
        $lat     = (float) $request->input('lat', 0);
        $lng     = (float) $request->input('lng', 0);
        $radius  = (float) $request->input('radius', 10);
        $domaine = $request->input('domaine');
        $limit   = (int)   $request->input('limit', 8);

        if (!$lat || !$lng) {
            return response()->json(['message' => 'lat et lng requis'], 400);
        }

        $haversineWhere = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(entreprises.latitude))
            * cos(radians(entreprises.longitude) - radians({$lng}))
            + sin(radians({$lat})) * sin(radians(entreprises.latitude))
        ))";

        $services = $this->buildNearbyQuery($haversineWhere, $lat, $lng, $radius, $domaine, $limit);

        // Si aucun résultat, retenter avec rayon ×3
        if ($services->isEmpty() && $radius < 50) {
            $bigRadius = min($radius * 3, 100);
            $services  = $this->buildNearbyQuery($haversineWhere, $lat, $lng, $bigRadius, $domaine, $limit);
        }

        return response()->json([
            'data'  => $services->map(fn($s) => $this->formatNearby($s)),
            'count' => $services->count(),
        ]);
    }

    private function buildNearbyQuery(string $haversineWhere, float $lat, float $lng, float $radius, ?string $domaine, int $limit) {
        $abonnementSub = $this->abonnementPayantSub();

        $query = Service::with(['entreprise', 'domaine'])
            ->join('entreprises', 'services.entreprise_id', '=', 'entreprises.id')
            ->join('domaines',    'services.domaine_id',    '=', 'domaines.id')
            ->where('entreprises.status', 'validated')
            ->whereNotNull('entreprises.latitude')
            ->whereNotNull('entreprises.longitude')
            // FIX PostgreSQL boolean : whereRaw au lieu de where('col', true)
            ->whereRaw('"services"."is_visibility" = true')
            ->whereHas('entreprise', fn($q) => $q->where('status', 'validated')->visible())
            ->leftJoinSub($abonnementSub, 'abo_payant', function ($join) {
                $join->on('services.entreprise_id', '=', 'abo_payant.entreprise_id');
            })
            ->selectRaw("services.*, {$haversineWhere} AS distance_km")
            ->whereRaw("{$haversineWhere} <= ?", [$radius])
            // Priorité : abonnement payant d'abord, puis plus cher, puis plus proche
            ->orderByRaw('CASE WHEN abo_payant.entreprise_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(abo_payant.plan_price, 0) DESC')
            ->orderByRaw("{$haversineWhere} ASC");

        if ($domaine) {
            $query->where('domaines.name', 'like', "%{$domaine}%");
        }

        return $query->limit($limit)->get();
    }

    public function index(Request $request) {
        $abonnementSub = $this->abonnementPayantSub();

        $query = Service::with(['entreprise', 'domaine'])
            // FIX PostgreSQL boolean : whereRaw au lieu de where('col', true)
            ->whereRaw('"is_visibility" = true')
            ->whereHas('entreprise', fn($q) => $q->where('status', 'validated')->visible())
            ->leftJoinSub($abonnementSub, 'abo_payant', function ($join) {
                $join->on('services.entreprise_id', '=', 'abo_payant.entreprise_id');
            })
            // Priorité : abonnement payant d'abord, puis plus cher, puis plus récent
            ->orderByRaw('CASE WHEN abo_payant.entreprise_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(abo_payant.plan_price, 0) DESC')
            ->orderBy('services.updated_at', 'desc')
            ->select('services.*');

        if ($request->filled('domaine')) {
            $query->whereHas('domaine', fn($q) =>
                $q->where('name', 'like', "%{$request->domaine}%")
            );
        }

        $services = $query->limit((int) $request->input('limit', 20))->get();

        return response()->json([
            'data' => $services->map(fn($s) => $this->formatIndex($s)),
        ]);
    }

    public function domaines()
    {
        return response()->json([
            'data' => Domaine::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatNearby($service): array  {
        return [
            'id'                  => $service->id,
            'name'                => $service->name,
            'start_time'          => $service->start_time,
            'end_time'            => $service->end_time,
            'is_open_24h'         => (bool) $service->is_open_24h,
            'is_always_open'      => (bool) $service->is_always_open,
            'price'               => $service->price,
            'price_promo'         => $service->price_promo,
            'has_promo'           => (bool) $service->has_promo,
            'is_price_on_request' => (bool) $service->is_price_on_request,
            'descriptions'        => $service->descriptions,
            'distance_km'         => round((float) $service->distance_km, 1),
            'domaine'             => $service->domaine?->name,
            'entreprise'          => $this->formatEntreprise($service->entreprise),
        ];
    }

    private function formatIndex($service): array   {
        return [
            'id'                  => $service->id,
            'name'                => $service->name,
            'start_time'          => $service->start_time,
            'end_time'            => $service->end_time,
            'is_open_24h'         => (bool) $service->is_open_24h,
            'is_always_open'      => (bool) $service->is_always_open,
            'price'               => $service->price,
            'price_promo'         => $service->price_promo,
            'has_promo'           => (bool) $service->has_promo,
            'is_price_on_request' => (bool) $service->is_price_on_request,
            'descriptions'        => $service->descriptions,
            'distance_km'         => null,
            'domaine'             => $service->domaine?->name,
            'entreprise'          => $this->formatEntreprise($service->entreprise),
        ];
    }

    private function formatEntreprise($entreprise): array  {
        return [
            'id'                       => $entreprise?->id,
            'name'                     => $entreprise?->name,
            'latitude'                 => $entreprise?->latitude,
            'longitude'                => $entreprise?->longitude,
            'google_formatted_address' => $entreprise?->google_formatted_address,
            'call_phone'               => $entreprise?->call_phone,
            'whatsapp_phone'           => $entreprise?->whatsapp_phone,
            'status_online'            => (bool) ($entreprise?->status_online ?? false),
            'logo'                     => $entreprise?->logo,
        ];
    }
}