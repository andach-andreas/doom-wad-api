@extends('template')

@section('title', 'Commands')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 p-4 text-red-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($commands as $name => $definition)
            @php $last = $lastRuns->get($name); @endphp
            <div class="rounded-lg bg-white dark:bg-slate-800 p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $definition['label'] }}</h2>
                        <p class="font-mono text-xs text-slate-500">php artisan {{ $name }}</p>
                    </div>
                    @if ($last)
                        @include('admin.commands._status', ['status' => $last->status])
                    @endif
                </div>

                <p class="mt-3 text-sm">{{ $definition['description'] }}</p>

                @if ($last)
                    <p class="mt-2 text-xs text-slate-500">
                        Last run:
                        <a href="{{ route('admin.commands.show', $last) }}" class="underline">
                            {{ $last->created_at->diffForHumans() }}
                        </a>
                        @if ($last->arguments)
                            ({{ implode(' ', $last->arguments) }})
                        @endif
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.commands.store') }}" class="mt-4 flex gap-2"
                      onsubmit="return confirm('Run {{ $name }}?')">
                    @csrf
                    <input type="hidden" name="command" value="{{ $name }}">
                    @isset($definition['argument'])
                        <input type="text" name="argument" required placeholder="{{ $definition['argument'] }}"
                               class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                    @endisset
                    <button type="submit"
                            class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50"
                            @if ($last?->isActive() && !isset($definition['argument'])) disabled title="Already queued or running" @endif>
                        Run
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <h2 class="mt-12 mb-4 text-2xl font-bold text-slate-800 dark:text-slate-100">Recent runs</h2>

    @if ($recentRuns->isEmpty())
        <p>Nothing has been run yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-300">
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4">Command</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Queued</th>
                        <th class="py-2 pr-4">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentRuns as $run)
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <td class="py-2 pr-4">
                                <a href="{{ route('admin.commands.show', $run) }}" class="underline">{{ $run->id }}</a>
                            </td>
                            <td class="py-2 pr-4 font-mono">{{ $run->commandLine() }}</td>
                            <td class="py-2 pr-4">@include('admin.commands._status', ['status' => $run->status])</td>
                            <td class="py-2 pr-4">{{ $run->created_at->diffForHumans() }}</td>
                            <td class="py-2 pr-4">{{ $run->duration() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
