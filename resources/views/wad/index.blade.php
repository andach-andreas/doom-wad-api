@extends('template')

@section('title', 'WADs List/Search')

@section('content')
    <div class="container mx-auto p-4">
        <x-andach-card title="Search" class="mb-4">
            <form method="GET" action="{{ route('wad.index') }}" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <input type="text" name="filename" value="{{ request('filename') }}" placeholder="Filename"
                           class="border rounded px-3 py-2 w-full" />

                    <input type="text" name="title" value="{{ request('title') }}" placeholder="Title"
                           class="border rounded px-3 py-2 w-full" />

                    <input type="text" name="author" value="{{ request('author') }}" placeholder="Author"
                           class="border rounded px-3 py-2 w-full" />

                    <input type="number" name="maps_count_min" value="{{ request('maps_count_min') }}" placeholder="Min Maps"
                           class="border rounded px-3 py-2 w-full" />

                    <input type="number" name="maps_count_max" value="{{ request('maps_count_max') }}" placeholder="Max Maps"
                           class="border rounded px-3 py-2 w-full" />

                    <select name="complevel" class="border rounded px-3 py-2 w-full">
                        <option value="">Any Complevel</option>
                        @for ($i = 1; $i <= 21; $i++)
                            <option value="{{ $i }}" {{ request('complevel') == $i ? 'selected' : '' }}>
                                {{ $i }}
                            </option>
                        @endfor
                    </select>

                    <select name="iwad" class="border rounded px-3 py-2 w-full">
                        <option value="">Any IWAD</option>
                        <option value="doom" {{ request('iwad') == 'doom' ? 'selected' : '' }}>Doom</option>
                        <option value="doom2" {{ request('iwad') == 'doom2' ? 'selected' : '' }}>Doom 2</option>
                    </select>
                </div>

                <div class="mt-4">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Search
                    </button>
                </div>
            </form>
        </x-andach-card>

        <x-andach-table>
            <x-andach-thead>
                <tr>
                    <x-andach-th class="px-4 py-2">Filename</x-andach-th>
                    <x-andach-th class="px-4 py-2">Title</x-andach-th>
                    <x-andach-th class="px-4 py-2">Author</x-andach-th>
                    <x-andach-th class="px-4 py-2">Maps</x-andach-th>
                    <x-andach-th class="px-4 py-2">Complevel</x-andach-th>
                    <x-andach-th class="px-4 py-2">IWAD</x-andach-th>
                    <x-andach-th class="px-4 py-2">Single Player</x-andach-th>
                    <x-andach-th class="px-4 py-2">Co-Op</x-andach-th>
                    <x-andach-th class="px-4 py-2">Deathmatch</x-andach-th>
                </tr>
            </x-andach-thead>
            <x-andach-tbody>
            @foreach($wads as $wad)
                <tr class="border-t">
                    <x-andach-td class="px-4 py-2"><a href="{{ route('wad.show', $wad->id) }}">{{ $wad->filename }}</a></x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ Str::limit($wad->title, 50) }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->author }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->maps_count }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->complevel }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->iwad }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->single_player }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->coop }}</x-andach-td>
                    <x-andach-td class="px-4 py-2">{{ $wad->deathmatch }}</x-andach-td>
                </tr>
            @endforeach
            </x-andach-tbody>
        </x-andach-table>

        <div class="mt-4">
            @if (count($wads))
                {{ $wads->links() }}
            @endif
        </div>
    </div>
@endsection
