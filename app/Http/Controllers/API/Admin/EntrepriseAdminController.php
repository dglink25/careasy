<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Notifications\EntrepriseStatusChangedNotification;
use Illuminate\Support\Facades\Auth;

class EntrepriseAdminController extends Controller
{
    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(response()->json(['message' => 'Unauthorized. Admin only.'], 403));
        }
    }

    // lister toutes les demandes (avec filtrage)
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = Entreprise::with(['prestataire','domaines','services']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($sub){
                // closure set below to avoid injection; we'll use SQL after
            });
            $query->where(function($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('pdg_full_name', 'like', "%{$q}%");
            });
        }

        $entreprises = $query->orderBy('created_at','desc')->get();

        return response()->json(['data' => $entreprises]);
    }

    // voir une entreprise (avec fichiers)
    public function show($id){
        $this->ensureAdmin();

        $entreprise = Entreprise::with(['prestataire','domaines','services'])->find($id);
        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        return response()->json($entreprise);
    }

    // valider
    public function approve(Request $request, $id){
        $this->ensureAdmin();

        $entreprise = Entreprise::find($id);
        if (!$entreprise) {
            return response()->json(['message'=>'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'validated') {
            return response()->json(['message' => 'Entreprise déjà validée'], 400);
        }

        DB::beginTransaction();
        try {
            $entreprise->status = 'validated';
            $entreprise->admin_note = $request->admin_note ?? null;
            $entreprise->save();

            // notifier le prestataire
            $entreprise->prestataire->notify(new EntrepriseStatusChangedNotification($entreprise, 'validée', $entreprise->admin_note));

            DB::commit();

            return response()->json(['message' => 'Entreprise validée', 'entreprise' => $entreprise]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la validation', 'error' => $e->getMessage()], 500);
        }
    }

    // rejeter
    public function reject(Request $request, $id){
        $this->ensureAdmin();

        $request->validate([
            'admin_note' => 'required|string'
        ]);

        $entreprise = Entreprise::find($id);
        if (!$entreprise) {
            return response()->json(['message'=>'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'rejected') {
            return response()->json(['message' => 'Entreprise déjà rejetée'], 400);
        }

        DB::beginTransaction();
        try {
            $entreprise->status = 'rejected';
            $entreprise->admin_note = $request->admin_note;
            $entreprise->save();

            // notifier le prestataire
            $entreprise->prestataire->notify(new EntrepriseStatusChangedNotification($entreprise, 'rejetée', $entreprise->admin_note));

            DB::commit();

            return response()->json(['message' => 'Entreprise rejetée', 'entreprise' => $entreprise]);
        } 
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors du rejet', 'error' => $e->getMessage()], 500);
        }
    }
}

