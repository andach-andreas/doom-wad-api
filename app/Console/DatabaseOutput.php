<?php

namespace App\Console;

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

    public function __construct(private CommandRun $run)
    {
        parent::__construct(self::VERBOSITY_NORMAL, false);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->buffer .= $message . ($newline ? PHP_EOL : '');

        if (microtime(true) - $this->lastFlush >= self::FLUSH_INTERVAL) {
            $this->flush();
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
