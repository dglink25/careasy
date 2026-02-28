<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiLocationController extends Controller
{
    public function search(Request $request)
    {
        $q     = $request->input('q', '');
        $limit = (int) $request->input('limit', 5);
        if (empty($q)) return response()->json(['data' => []]);

        $results = DB::table('locations_benin')
            ->where('arrondissement', 'like', "%{$q}%")
            ->orWhere('commune',      'like', "%{$q}%")
            ->orWhere('departement',  'like', "%{$q}%")
            ->limit($limit)->get();

        return response()->json(['data' => $results]);
    }

    public function communes()
    {
        $communes = DB::table('locations_benin')
            ->distinct()->orderBy('commune')->pluck('commune');
        return response()->json(['communes' => $communes]);
    }
}