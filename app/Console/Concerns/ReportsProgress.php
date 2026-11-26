<?php

namespace App\Console\Concerns;

use App\Exceptions\CommandCancelledException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared progress/diagnostic output for the long-running WAD commands.
 *
 * - Everything printed is also written to storage/logs/commands-YYYY-MM-DD.log.
 * - Errors additionally go to the default log (laravel.log) with full context.
 * - begin() prints the environment and checks each disk is usable, so problems
 *   like an unwritable mount fail loudly at the start rather than silently.
 */
trait ReportsProgress
{
    private float $progressStartedAt = 0.0;
    private string $progressTitle = '';

    /** @var array<string, int> */
    private array $tallies = [];

    /**
     * Print a header and check the given disks. Returns false if a disk is unusable.
     */
    protected function begin(string $title, array $disks = []): bool
    {
        $this->progressStartedAt = microtime(true);
        $this->progressTitle = $title;
        $this->tallies = [];

        $this->progress("=== {$title} ===");
        $this->progress(sprintf(
            'PHP %s | user %s | pid %d | memory limit %s',
            PHP_VERSION,
            $this->currentUser(),
            getmypid(),
            ini_get('memory_limit'),
        ));

        foreach ($disks as $disk) {
            if (!$this->checkDisk($disk)) {
                $this->progress("Aborting: disk [{$disk}] is not usable.");

                return false;
            }
        }

        return true;
    }

    /**
     * Print the tallies and elapsed time. Returns the command exit code:
     * FAILURE if anything was tallied as 'failed', so the run shows as failed.
     */
    protected function finish(): int
    {
        $elapsed = (int) round(microtime(true) - $this->progressStartedAt);
        $summary = collect($this->tallies)->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ');

        $this->progress(sprintf(
            '=== %s finished in %s%s ===',
            $this->progressTitle,
            gmdate('H:i:s', $elapsed),
            $summary ? " | {$summary}" : '',
        ));

        return ($this->tallies['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function progress(string $message): void
    {
        $this->line($message);
        Log::channel('commands')->info($message);
    }

    protected function warnProgress(string $message): void
    {
        $this->warn($message);
        Log::channel('commands')->warning($message);
    }

    /**
     * Report a failure: printed, and logged to both commands.log and laravel.log.
     * Re-throws a stop request so a per-item catch can't swallow it.
     */
    protected function reportFailure(string $message, ?Throwable $e = null, array $context = []): void
    {
        if ($e instanceof CommandCancelledException) {
            throw $e;
        }

        $detail = $e ? sprintf(' — %s: %s (%s:%d)', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()) : '';

        $this->error('ERROR: ' . $message . $detail);

        $context = array_merge(['command' => $this->getName()], $context);

        if ($e) {
            $context['exception'] = $e;
        }

        Log::channel('commands')->error($message, $context);
        Log::error("[{$this->getName()}] {$message}", $context);
    }

    protected function tally(string $key, int $by = 1): void
    {
        $this->tallies[$key] = ($this->tallies[$key] ?? 0) + $by;
    }

    protected function humanBytes(int|float|false|null $bytes): string
    {
        if ($bytes === false || $bytes === null) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    private function checkDisk(string $disk): bool
    {
        $root = config("filesystems.disks.{$disk}.root");

        if (!$root) {
            $this->reportFailure("Disk [{$disk}] has no root configured.");

            return false;
        }

        if (!is_dir($root)) {
            $this->progress("Disk [{$disk}] {$root} does not exist; creating it.");

            if (!@mkdir($root, 0755, true) && !is_dir($root)) {
                $this->reportFailure(
                    "Disk [{$disk}] could not create {$root}: " . (error_get_last()['message'] ?? 'unknown error')
                    . " (running as {$this->currentUser()}; parent owned by {$this->ownerOf(dirname($root))})"
                );

                return false;
            }
        }

        if (!is_writable($root)) {
            $this->reportFailure(
                "Disk [{$disk}] {$root} is not writable by {$this->currentUser()} (owned by {$this->ownerOf($root)})."
            );

            return false;
        }

        $free = @disk_free_space($root);
        $this->progress("Disk [{$disk}] {$root} | writable | {$this->humanBytes($free)} free");

        if ($free !== false && $free < 1024 ** 3) {
            $this->warnProgress("Disk [{$disk}] has less than 1 GB free.");
        }

        return true;
    }

    private function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid();
        }

        return get_current_user();
    }

    private function ownerOf(string $path): string
    {
        $uid = @fileowner($path);

        if ($uid === false) {
            return 'unknown';
        }

        if (function_exists('posix_getpwuid')) {
            return posix_getpwuid($uid)['name'] ?? (string) $uid;
        }

        return (string) $uid;
    }
}
