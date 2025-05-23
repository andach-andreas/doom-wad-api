<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Map;
use App\Models\Wad;
use Illuminate\Http\Request;

class MapApiController extends Controller
{
    public function index(Request $request, string $filename, string $internalName)
    {
        $wad = Wad::where('filename', 'like', '%' . $filename . '%')->first();

        if (!$wad) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'WAD not Found',
            ], 404);
        }

        $map = Map::where('wad_id', $wad->id)
            ->where('internal_name', $internalName)
            ->first();

        if (!$map) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Map not Present in WAD',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $map,
        ]);
    }
}
