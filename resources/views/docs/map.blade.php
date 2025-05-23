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
        "map": {
            "id": 7350,
            "wad_id": 2869,
            "internal_name": "E1M1",
            "name": "",
            "count_things": 7,
            "count_linedefs": 7,
            "count_sidedefs": 7,
            "count_vertexes": 8,
            "count_sectors": 1,
            "created_at": null,
            "updated_at": null,
            "image_url": "http://localhost:36663/storage/maps/doom/0-9/0000h1slv/E1M1.png",
        },
        "demos": [...]
    }
}
        </pre>

        <p>Demos is an array with individual items like:</p>

        <pre>
{
    "id": 77032,
    "map_id": "2",
    "wad_id": 2,
    "category": "UV Max",
    "player": "Andrea Rovenski",
    "engine": "DSDA-Doom v0.25.6cl3",
    "note": "",
    "time": "0:36.26",
    "lmp_url": "https://dsdarchive.com/files/demos/0000h1slv/77032/0000h1slv-36.zip",
    "youtube_id": "YrEq9nT5hno",
    "youtube_link": "https://www.youtube.com/watch?v=YrEq9nT5hno",
    "comment": null
},
        </pre>
    </x-andach-card>
@endsection
