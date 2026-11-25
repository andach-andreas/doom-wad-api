<?php

namespace App\Jobs;

use App\Console\DatabaseOutput;
use App\Exceptions\CommandCancelledException;
use App\Models\CommandRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RunArtisanCommand implements ShouldQueue
{
    use Queueable;

    /** Never retry: these commands are long and not safe to run twice concurrently. */
    public int $tries = 1;

    /**
     * Six hours. A job-level timeout overrides the worker's --timeout flag, so
     * the standard Forge worker will let these commands run to completion.
     */
    public int $timeout = 21600;

    public function __construct(public CommandRun $run)
    {
    }

    public function handle(): void
    {
        if (!array_key_exists($this->run->command, CommandRun::COMMANDS)) {
            $this->run->update([
                'status' => CommandRun::STATUS_FAILED,
                'output' => "Command [{$this->run->command}] is not whitelisted.",
                'finished_at' => now(),
            ]);

            return;
        }

        // Only start if the run is still queued; Stop may have been pressed while it waited.
        $started = $this->run->transition(CommandRun::STATUS_QUEUED, CommandRun::STATUS_RUNNING, [
            'started_at' => now(),
            'pid' => getmypid(),
            'queue_job_id' => $this->job?->getJobId(),
        ]);

        if (!$started) {
            return;
        }

        $output = new DatabaseOutput($this->run);
        $output->writeln('Started ' . now()->toDateTimeString() . ' (pid ' . getmypid() . ', memory limit ' . ini_get('memory_limit') . ')');
        $output->flush();

        try {
            $exitCode = Artisan::call($this->run->command, $this->buildParameters(), $output);
        } catch (CommandCancelledException $e) {
            $output->forceWriteln('');
            $output->forceWriteln('STOPPED: ' . $e->getMessage());
            $output->flush();

            $this->run->update([
                'status' => CommandRun::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);

            return;
        } catch (Throwable $e) {
            $output->writeln('');
            $output->writeln('EXCEPTION: ' . $e->getMessage());
            $output->writeln($e->getFile() . ':' . $e->getLine());
            $output->flush();

            $this->run->update([
                'status' => CommandRun::STATUS_FAILED,
                'exit_code' => 1,
                'finished_at' => now(),
            ]);

            return;
        }

        $output->flush();

        // If Stop was pressed but the command finished before writing again, honour the stop.
        if ($this->run->fresh()->status === CommandRun::STATUS_CANCELLING) {
            $this->run->update(['status' => CommandRun::STATUS_CANCELLED, 'exit_code' => $exitCode, 'finished_at' => now()]);

            return;
        }

        $this->run->update([
            'status' => $exitCode === 0 ? CommandRun::STATUS_DONE : CommandRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'finished_at' => now(),
        ]);
    }

    /**
     * Called when the worker kills the job (e.g. timeout) rather than handle() returning.
     */
    public function failed(?Throwable $e): void
    {
        $this->run->refresh();

        // A force-killed run is already marked cancelled; don't relabel it as failed
        // when the queue later notices the dead job.
        if ($this->run->status === CommandRun::STATUS_CANCELLED) {
            return;
        }

        $this->run->update([
            'status' => CommandRun::STATUS_FAILED,
            'output' => $this->run->output . PHP_EOL . 'JOB FAILED: ' . ($e?->getMessage() ?? 'unknown error'),
            'finished_at' => now(),
        ]);
    }

    private function buildParameters(): array
    {
        $definition = CommandRun::COMMANDS[$this->run->command];

        if (isset($definition['argument'])) {
            return [$definition['argument'] => $this->run->arguments[0] ?? null];
        }

        return [];
    }
}
