<?php

namespace App\Jobs;

use App\Console\DatabaseOutput;
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

    /** Six hours. Must be lower than the `long` connection's retry_after. */
    public int $timeout = 21600;

    public function __construct(public CommandRun $run)
    {
        $this->onConnection('long');
        $this->onQueue('long');
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

        $this->run->update([
            'status' => CommandRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $output = new DatabaseOutput($this->run);

        try {
            $exitCode = Artisan::call($this->run->command, $this->buildParameters(), $output);
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
