<?php

namespace App\Http\Controllers;

use App\Models\Wad;
use Illuminate\Http\Request;

class WadController extends Controller
{
    public function index(Request $request)
    {
        $query = Wad::query();

        if ($request->filled('filename')) {
            $query->where('filename', 'like', '%' . $request->filename . '%');
        }

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        if ($request->filled('author')) {
            $query->where('author', 'like', '%' . $request->author . '%');
        }

        if ($request->filled('maps_count_min')) {
            $query->where('maps_count', '>=', $request->maps_count_min);
        }

        if ($request->filled('maps_count_max')) {
            $query->where('maps_count', '<=', $request->maps_count_max);
        }

        if ($request->filled('complevel')) {
            $query->where('complevel', $request->complevel);
        }

        if ($request->filled('iwad')) {
            $query->where('iwad', $request->iwad);
        }

        $wads = $query->paginate(15)->appends($request->query());

        return view('wad.index', compact('wads'));
    }

    public function show($id)
    {
        $wad = Wad::findOrFail($id);

        return view('wad.show', compact('wad'));
    }

    public function updateDemos($id)
    {
        $wad = Wad::findOrFail($id);

        return $wad->updateDemos();
    }
}
