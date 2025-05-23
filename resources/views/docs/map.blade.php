@extends('template')

@section('title', 'API Docs - Map')

@section('content')
    <x-andach-card title="Map Requests">
        <p>This is a simple, unauthenticated GET api request. Full searching is not yet implemented. The correct syntax to get map data is:</p>
        <pre class="mt-4">GET https://doomwadsapi.andach.co.uk/api/v1/map/FILENAME/MAPID</pre>
        <p class="mt-4">FILENAME should be the filename of the string without any extensions, for example, "doom" or "doom2". It is not case-sensitive.</p>
        <p>MAPID should in the form ExMx or MAPxx depending on whether the map uses the Doom or Doom2 IWAD.</p>
    </x-andach-card>

    <x-andach-card title="Sample Output">
        <pre>
{
    "status": "success",
    "data": {
        "id": 7350,
        "wad_id": 2869,
        "internal_name": "E1M1",
        "name": "",
        "image_path": "doom/s-u/test/TEST/E1M1.png",
        "count_things": 7,
        "count_linedefs": 7,
        "count_sidedefs": 7,
        "count_vertexes": 8,
        "count_sectors": 1,
        "created_at": null,
        "updated_at": null
    }
}
        </pre>
    </x-andach-card>
@endsection
