<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wad;
use Illuminate\Http\Request;

class WadApiController extends Controller
{
    public function index(string $foldername): \Illuminate\Http\JsonResponse
    {
        $wad = Wad::where('foldername', 'like', '%' . $foldername . '%')->first();

        if (!$wad) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'WAD not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'wad' => $wad,
                'maps' => $wad->maps,
            ],
        ]);
    }
}
