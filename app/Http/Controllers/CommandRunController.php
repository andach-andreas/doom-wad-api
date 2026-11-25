<?php

namespace App\Http\Controllers;

use App\Jobs\RunArtisanCommand;
use App\Models\CommandRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CommandRunController extends Controller
{
    public function index()
    {
        $lastRuns = CommandRun::query()
            ->whereIn('id', CommandRun::query()->selectRaw('max(id)')->groupBy('command'))
            ->get()
            ->keyBy('command');

        $recentRuns = CommandRun::latest('id')->limit(25)->get();

        return view('admin.commands.index', [
            'commands' => CommandRun::COMMANDS,
            'lastRuns' => $lastRuns,
            'recentRuns' => $recentRuns,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'command' => ['required', Rule::in(array_keys(CommandRun::COMMANDS))],
            'argument' => ['nullable', 'string', 'max:255'],
        ]);

        $command = $validated['command'];
        $definition = CommandRun::COMMANDS[$command];
        $arguments = [];

        if (isset($definition['argument'])) {
            $request->validate(['argument' => ['required']]);
            $arguments = [trim($validated['argument'])];
        }

        // Lock briefly so a double-click can't queue the same command twice.
        $lock = Cache::lock('command-run:' . $command . ':' . implode(' ', $arguments), 10);

        if (!$lock->get()) {
            return back()->withErrors(['command' => 'That command is already being queued.']);
        }

        try {
            $existing = CommandRun::active()
                ->where('command', $command)
                ->get()
                ->first(fn (CommandRun $run) => ($run->arguments ?? []) === $arguments);

            if ($existing) {
                return redirect()
                    ->route('admin.commands.show', $existing)
                    ->with('status', 'That command is already queued or running.');
            }

            $run = CommandRun::create([
                'command' => $command,
                'arguments' => $arguments,
                'status' => CommandRun::STATUS_QUEUED,
            ]);

            RunArtisanCommand::dispatch($run);
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.commands.show', $run);
    }

    public function show(CommandRun $run)
    {
        return view('admin.commands.show', ['run' => $run]);
    }

    public function output(CommandRun $run)
    {
        return response()->json([
            'status' => $run->status,
            'exit_code' => $run->exit_code,
            'duration' => $run->duration(),
            'output' => $run->output ?? '',
        ]);
    }
}
