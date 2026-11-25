<?php

namespace App\Console;

use App\Exceptions\CommandCancelledException;
use App\Models\CommandRun;
use Symfony\Component\Console\Output\Output;

/**
 * Console output that streams into a CommandRun's `output` column, so the
 * admin page can show progress while a queued command is still running.
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

    /** Write a line even after cancellation (used by the job to record the stop). */
    public function forceWriteln(string $message): void
    {
        $this->buffer .= $message . PHP_EOL;
    }

    public function __construct(private CommandRun $run)
    {
        parent::__construct(self::VERBOSITY_NORMAL, false);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        // Once cancelled, every write throws. Commands that catch \Throwable around
        // a unit of work (e.g. wads:analyse) then still unwind on their next write.
        if ($this->cancelled) {
            throw new CommandCancelledException('Stopped from the admin page.');
        }

        $this->buffer .= $message . ($newline ? PHP_EOL : '');

        if (microtime(true) - $this->lastFlush >= self::FLUSH_INTERVAL) {
            $this->flush();
            $this->throwIfCancelled();
        }
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
            $this->buffer = "[... earlier output trimmed ...]\n" . substr($this->buffer, -self::MAX_BYTES);
        }

        $this->run->forceFill(['output' => $this->buffer])->saveQuietly();
        $this->lastFlush = microtime(true);
    }
}
