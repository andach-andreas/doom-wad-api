@extends('template')

@section('content')
    <div class="container mx-auto p-4">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <h1 class="text-2xl font-bold">{{ $wad->title ?? $wad->filename }}</h1>

            @if ($wad->foldername)
                <form method="POST" action="{{ route('admin.commands.store') }}">
                    @csrf
                    <input type="hidden" name="command" value="dsda:sync">
                    <input type="hidden" name="argument" value="{{ $wad->foldername }}">
                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Sync from DSDA
                    </button>
                </form>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p><strong>Filename:</strong> {{ $wad->filename_with_extension }}</p>
                <p><strong>Author:</strong> {{ $wad->author }}</p>
                <p><strong>Release Date:</strong> {{ $wad->release_date }}</p>
                <p><strong>IWAD:</strong> {{ $wad->iwad }}</p>
                <p><strong>Primary Purpose:</strong> {{ $wad->primary_purpose }}</p>
                <p><strong>Game:</strong> {{ $wad->game }}</p>
                <p><strong>Complevel:</strong> {{ $wad->complevel }}</p>
            </div>
            <div>
                <p><strong>Maps:</strong> {{ $wad->maps_count }}</p>
                <p><strong>Linedefs:</strong> {{ $wad->linedefs_count }}</p>
                <p><strong>Sectors:</strong> {{ $wad->sectors_count }}</p>
                <p><strong>Textures:</strong> {{ $wad->textures_count }}</p>
                <p><strong>Things:</strong> {{ $wad->things_count }}</p>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="text-xl font-semibold">Description</h2>
            <p class="mt-2 whitespace-pre-line">{{ $wad->description }}</p>
        </div>
    </div>
@endsection
