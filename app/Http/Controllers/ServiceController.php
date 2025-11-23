<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller{
    public function index()
    {
        return Service::with('entreprise', 'domaine')->get();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'prestataire_id' => 'required|exists:users,id',
            'domaine_id' => 'required|exists:domaines,id',
            'name' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'price' => 'nullable|numeric',
            'descriptions' => 'nullable|string',
            'medias' => 'nullable|array',
            'is_open_24h' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::create($request->all());
        return response()->json(['message' => 'Service créé avec succès', 'service' => $service], 201);
    }

    public function show($id)
    {
        $service = Service::with('entreprise', 'domaine')->find($id);

        if (!$service) {
            return response()->json(['message' => 'Service non trouvé'], 404);
        }

        return response()->json($service);
    }
}
