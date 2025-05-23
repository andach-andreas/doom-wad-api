@extends('template')

@section('title', 'API Docs - Wad')

@section('content')
    <x-andach-card title="Map Requests">
        <p>This is a simple, unauthenticated GET api request. Full searching is not yet implemented. The correct syntax to get wad data is:</p>
        <pre class="mt-4">GET https://doomwadsapi.andach.co.uk/api/v1/wad/FILENAME</pre>
        <p class="mt-4">FILENAME should be the filename of the string without any extensions, for example, "doom" or "doom2". It is not case-sensitive.</p>
    </x-andach-card>

    <x-andach-card title="HTTP Statuses">
        <p>HTTP status will be 200 for a successful output, 404 if the filename could not be found.</p>
    </x-andach-card>

    <x-andach-card title="Sample Output (Success)">
        <pre>
{
    "status": "success",
    "data": {
        "wad": {
            "id": 2869,
            "filename": "test",
            "filename_with_extension": "test.wad",
            "complevel": null,
            "maps_count": 1,
            "linedefs_count": 7,
            "sidedefs_count": 7,
            "vertexes_count": 8,
            "textures_count": null,
            "things_count": 7,
            "sectors_count": 1,
            "iwad": "doom",
            "archive_maintainer": "",
            "update_to": "",
            "advanced_engine_needed": "Doom1",
            "primary_purpose": "Single play",
            "title": "Test Level",
            "release_date": "06/26/2006",
            "author": "Lucent97",
            "email_address": "Lucent97@gmail.com",
            "other_files_by_author": "",
            "misc_author_info": "not new to the scene, just new to publishing\nlevels",
            "description": "a \"refresher\" level to help me get back into\nthe swing of things for doom levels. Figured\ni'd start small, and work my way up. Enjoy it\nfor the 1 minute you'll be playing it!",
            "credits": "Doomworld.com, for supporting my addiction",
            "new_levels": "1",
            "sounds": "No",
            "music": "No",
            "graphics": "No",
            "dehacked_patch": "No",
            "demos": "No",
            "other": "No",
            "other_files_required": "None",
            "game": "Doom",
            "map": "E1M1",
            "single_player": "Designed for",
            "coop": "",
            "deathmatch": "",
            "other_game_styles": "",
            "difficulty_settings": "Not implemented",
            "base": "New from scratch",
            "build_time": "Half-hour, would've been less if i figured out\nhow to get the \"things\" to appear in level\nsooner",
            "editors_used": "DeepSea",
            "known_bugs": "",
            "may_not_run_with": "",
            "tested_with": "",
            "where_to_get_web": "",
            "where_to_get_ftp": "",
        }
    }
}
        </pre>
    </x-andach-card>

    <x-andach-card title="Sample Output (Failure)">
        <pre>
{
    "status": "fail",
    "message": "WAD not found"
}
        </pre>
    </x-andach-card>
@endsection
