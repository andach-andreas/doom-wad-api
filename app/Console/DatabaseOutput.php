<?php

namespace App\Console;

use App\Exceptions\CommandCancelledException;
use App\Models\CommandRun;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

/**
 * Console output that streams into a CommandRun's `output` column, so the
 * admin page can show progress while a queued command is still running.
 *
 * Every line is also mirrored to the process's stdout, so it shows up in the
 * queue worker's log (Forge: Queue -> worker -> view log).
 */
class DatabaseOutput extends Output
{
    /** Keep at most this many bytes, trimming the oldest output. */
    private const MAX_BYTES = 1_000_000;

    /** Seconds between database writes. */
    private const FLUSH_INTERVAL = 1.0;

    private string $buffer = '';
    private float $lastFlush = 0.0;
    private bool $cancelled = false;
    private bool $saveFailureReported = false;
    private ?StreamOutput $mirror;

    public function __construct(private CommandRun $run)
    {
        parent::__construct(self::VERBOSITY_NORMAL, false);

        $stdout = @fopen('php://stdout', 'w');
        $this->mirror = $stdout ? new StreamOutput($stdout, self::VERBOSITY_NORMAL, false) : null;
    }

    /** Write a line even after cancellation (used by the job to record the stop). */
    public function forceWriteln(string $message): void
    {
        $this->append($message . PHP_EOL);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        // Once cancelled, every write throws. Commands that catch \Throwable around
        // a unit of work (e.g. wads:analyse) then still unwind on their next write.
        if ($this->cancelled) {
            throw new CommandCancelledException('Stopped from the admin page.');
        }

        $this->append($message . ($newline ? PHP_EOL : ''));

        if (microtime(true) - $this->lastFlush >= self::FLUSH_INTERVAL) {
            $this->flush();
            $this->throwIfCancelled();
        }
    }

    /**
     * Old idgames zips have DOS code-page file names, which end up in the output
     * as invalid UTF-8. MySQL rejects those, so scrub them to valid UTF-8 first.
     */
    private function append(string $text): void
    {
        $text = mb_scrub($text, 'UTF-8');

        $this->buffer .= $text;
        $this->mirror?->write($text, false);
    }

    /**
     * Abort the running command if Stop was pressed. Checked at most once per
     * flush interval, and only when the command writes output.
     */
    private function throwIfCancelled(): void
    {
        $status = CommandRun::whereKey($this->run->getKey())->value('status');

        if ($status === CommandRun::STATUS_CANCELLING) {
            $this->cancelled = true;

            throw new CommandCancelledException('Stopped from the admin page.');
        }
    }

    public function flush(): void
    {
        if (strlen($this->buffer) > self::MAX_BYTES) {
            // Byte-based cut can split a multibyte character, so scrub again.
            $this->buffer = "[... earlier output trimmed ...]\n" . mb_scrub(substr($this->buffer, -self::MAX_BYTES), 'UTF-8');
        }

        $this->lastFlush = microtime(true);

        // Failing to save progress must never kill the command itself: log it once
        // and carry on (the command's own logging still records everything).
        try {
            $this->run->forceFill(['output' => $this->buffer])->saveQuietly();
        } catch (Throwable $e) {
            if (!$this->saveFailureReported) {
                $this->saveFailureReported = true;
                Log::error("[command-run {$this->run->id}] could not save output to the database; continuing without it", ['exception' => $e]);
            }
        }
    }
}
