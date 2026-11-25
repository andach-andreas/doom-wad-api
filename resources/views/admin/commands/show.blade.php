@extends('template')

@section('title', $run->label())

@section('content')
    @if (session('status'))
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-amber-800">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-4 text-sm">
        <span class="font-mono">php artisan {{ $run->commandLine() }}</span>
        <span id="run-status">@include('admin.commands._status', ['status' => $run->status])</span>
        <span>Duration: <span id="run-duration">{{ $run->duration() ?? '—' }}</span></span>
        <span>Exit code: <span id="run-exit-code">{{ $run->exit_code ?? '—' }}</span></span>
        <a href="{{ route('admin.commands.index') }}" class="underline">Back to commands</a>
    </div>

    <label class="mb-2 flex items-center gap-2 text-sm">
        <input type="checkbox" id="follow" checked> Follow output
    </label>

    <pre id="run-output"
         class="h-[60vh] overflow-auto rounded bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-100 whitespace-pre-wrap">{{ $run->output ?: 'Waiting for the queue worker to pick this up...' }}</pre>

    @if ($run->isActive())
        <script>
            (function () {
                const url = @json(route('admin.commands.output', $run));
                const out = document.getElementById('run-output');
                const follow = document.getElementById('follow');
                const statusClasses = {
                    queued: 'bg-slate-200 text-slate-700',
                    running: 'bg-blue-100 text-blue-800',
                    done: 'bg-green-100 text-green-800',
                    failed: 'bg-red-100 text-red-800',
                };

                if (follow.checked) out.scrollTop = out.scrollHeight;

                async function poll() {
                    try {
                        const res = await fetch(url, { headers: { Accept: 'application/json' } });
                        const data = await res.json();

                        if (data.output) out.textContent = data.output;
                        if (follow.checked) out.scrollTop = out.scrollHeight;

                        const badge = document.querySelector('#run-status [data-status-badge]');
                        badge.textContent = data.status;
                        badge.className = 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold ' + (statusClasses[data.status] || '');
                        document.getElementById('run-duration').textContent = data.duration ?? '—';
                        document.getElementById('run-exit-code').textContent = data.exit_code ?? '—';

                        if (data.status === 'queued' || data.status === 'running') {
                            setTimeout(poll, 2000);
                        }
                    } catch (e) {
                        setTimeout(poll, 5000);
                    }
                }

                setTimeout(poll, 2000);
            })();
        </script>
    @endif
@endsection
