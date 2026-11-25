<?php

namespace App\Http\Controllers;

use App\Jobs\RunArtisanCommand;
use App\Models\CommandRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /**
     * Ask a run to stop. Queued runs are cancelled outright (the worker will skip
     * them); running ones are flagged and abort at their next output write.
     */
    public function cancel(CommandRun $run)
    {
        if ($run->transition(CommandRun::STATUS_QUEUED, CommandRun::STATUS_CANCELLED, ['finished_at' => now()])) {
            return back()->with('status', 'Cancelled before it started.');
        }

        if ($run->transition(CommandRun::STATUS_RUNNING, CommandRun::STATUS_CANCELLING)) {
            return back()->with('status', 'Stop requested. It will stop the next time the command writes output. If it is hung, use Force kill.');
        }

        return back()->with('status', 'That run is not active.');
    }

    /**
     * Last resort for a hung command: SIGKILL the queue worker process running it.
     * Supervisor (Forge) restarts the worker automatically.
     */
    public function kill(CommandRun $run)
    {
        if (!in_array($run->status, [CommandRun::STATUS_RUNNING, CommandRun::STATUS_CANCELLING], true)) {
            return back()->with('status', 'That run is not running, so there is nothing to kill.');
        }

        if (!$run->pid) {
            // No worker pid recorded (e.g. a run from before pids were tracked): nothing to signal.
            $this->markKilled($run, 'No worker process recorded for this run; marked as cancelled.');

            return back()->with('status', 'No worker process was recorded for this run, so it has just been marked as cancelled.');
        }

        if (!function_exists('posix_kill')) {
            return back()->withErrors(['kill' => 'Force kill needs the PHP posix extension (not available on this platform).']);
        }

        if (!$this->isQueueWorker($run->pid)) {
            // The worker has already gone (or the pid was reused): just tidy up.
            $this->markKilled($run, 'Worker process ' . $run->pid . ' no longer exists; marked as cancelled.');

            return back()->with('status', 'The worker process had already exited. Run marked as cancelled.');
        }

        if (!posix_kill($run->pid, 9)) {
            return back()->withErrors(['kill' => 'Could not kill process ' . $run->pid . ': ' . posix_strerror(posix_get_last_error())]);
        }

        $this->markKilled($run, 'Worker process ' . $run->pid . ' was force killed.');

        return back()->with('status', 'Worker killed. Forge will restart it.');
    }

    private function isQueueWorker(int $pid): bool
    {
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

        return $cmdline !== false && str_contains($cmdline, 'queue:work');
    }

    private function markKilled(CommandRun $run, string $message): void
    {
        $run->update([
            'status' => CommandRun::STATUS_CANCELLED,
            'output' => rtrim((string) $run->output) . PHP_EOL . PHP_EOL . 'KILLED: ' . $message . PHP_EOL,
            'finished_at' => now(),
        ]);

        // Remove the dead job's reserved row so the queue doesn't pick it up again later.
        if ($run->queue_job_id) {
            DB::table(config('queue.connections.database.table', 'jobs'))
                ->where('id', $run->queue_job_id)
                ->delete();
        }
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
