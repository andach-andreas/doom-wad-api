@php
    $classes = match ($status) {
        'queued' => 'bg-slate-200 text-slate-700',
        'running' => 'bg-blue-100 text-blue-800',
        'done' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        default => 'bg-slate-200 text-slate-700',
    };
@endphp
<span data-status-badge class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $classes }}">{{ $status }}</span>
