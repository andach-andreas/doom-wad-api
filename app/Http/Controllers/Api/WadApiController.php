<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wad;
use Illuminate\Http\Request;

class WadApiController extends Controller
{
    public function index(string $filename): \Illuminate\Http\JsonResponse
    {
        $wad = Wad::where('filename', 'like', '%' . $filename . '%')->first();

        if (!$wad) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'WAD not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $wad,
        ]);
    }
}
