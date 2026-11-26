<?php

namespace App\Console\Commands;

use App\Console\Concerns\ReportsProgress;
use App\Support\Idgames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use ZipArchive;

class UnzipWads extends Command
{
    use ReportsProgress;

    /** Suffix for a folder that is still being extracted. */
    private const PARTIAL_SUFFIX = '.__partial';

    private ?string $unzipBinary = null;

    protected $signature = 'wads:unzip';
    protected $description = 'Unzip WAD zip files into appropriate directories using Laravel Storage disks';

    public function handle()
    {
        if (!$this->begin('Unzip WADs', ['zips', 'wads'])) {
            return self::FAILURE;
        }

        $this->unzipBinary = (new ExecutableFinder())->find('unzip');

        if ($this->unzipBinary) {
            $this->progress("External unzip: {$this->unzipBinary} (used for legacy Implode/Shrink zips)");
        } else {
            $this->warnProgress('External unzip not found: legacy PKZIP 1.x zips (Implode/Shrink) will fail. Install it with: sudo apt install unzip');
        }

        $zips = Storage::disk('zips');
        $wads = Storage::disk('wads');

        // Work out what needs doing up front so progress can show [n/total].
        $this->checkpoint('Scanning zips to find what needs extracting...');
        $pending = [];
        $directories = Idgames::directories();

        foreach ($directories as $d => $dir) {
            $dirPending = count($pending);
            $dirZips = 0;

            foreach ($zips->files($dir) as $file) {
                if (!str_ends_with(strtolower($file), '.zip')) {
                    continue;
                }

                $dirZips++;
                $extractPath = $dir . '/' . pathinfo($file, PATHINFO_FILENAME);

                if ($wads->exists($extractPath)) {
                    $this->tally('already extracted');

                    continue;
                }

                $pending[] = [$file, $extractPath];
            }

            if ($dirZips > 0) {
                $this->progress(sprintf(
                    '  [dir %d/%d] %s: %d zips, %d to extract',
                    $d + 1,
                    count($directories),
                    $dir,
                    $dirZips,
                    count($pending) - $dirPending,
                ));
            }
        }

        $this->checkpoint(sprintf(
            '%d zips already extracted, %d to extract',
            $this->tallies['already extracted'] ?? 0,
            count($pending),
        ));

        foreach ($pending as $i => [$file, $extractPath]) {
            $this->extractOne($file, $extractPath, sprintf('[%d/%d]', $i + 1, count($pending)));
        }

        return $this->finish();
    }

    /**
     * Extract into "<name>.__partial" and rename only on success, so a failed or
     * interrupted extraction is retried next run instead of being treated as done.
     */
    private function extractOne(string $file, string $extractPath, string $prefix): void
    {
        $zipPath = Storage::disk('zips')->path($file);
        $finalDir = Storage::disk('wads')->path($extractPath);
        $partialDir = $finalDir . self::PARTIAL_SUFFIX;

        try {
            if (is_dir($partialDir)) {
                File::deleteDirectory($partialDir); // left over from an interrupted run
            }

            $zip = new ZipArchive();
            $openResult = $zip->open($zipPath);

            if ($openResult !== true) {
                throw new RuntimeException("could not open zip (ZipArchive error {$openResult}: {$this->zipErrorName($openResult)})");
            }

            $skipped = [];
            $via = '';

            // Early-90s PKZIP 1.x zips use Implode/Shrink/Reduce, which libzip can't
            // decompress. Hand those whole archives to Info-ZIP unzip instead.
            $legacyMethods = $this->unsupportedMethods($zip);

            if ($legacyMethods) {
                $zip->close();
                [$written, $wadCount] = $this->extractWithUnzip($zipPath, $partialDir);
                $via = ' via unzip (' . implode(', ', $legacyMethods) . ')';
                $this->tally('legacy zips');
            } else {
                try {
                    [$written, $wadCount, $skipped] = $this->extractWithZipArchive($zip, $partialDir);
                } finally {
                    $zip->close();
                }
            }

            if (!@rename($partialDir, $finalDir)) {
                throw new RuntimeException('could not rename extracted folder into place: ' . (error_get_last()['message'] ?? 'unknown error'));
            }
        } catch (Throwable $e) {
            if (is_dir($partialDir)) {
                File::deleteDirectory($partialDir);
            }

            $this->reportFailure("{$prefix} {$file}: extraction failed", $e, ['zip' => $zipPath]);
            $this->tally('failed');

            return;
        }

        $this->progress(sprintf(
            '%s Extracted %s -> %d files (%d .wad)%s%s',
            $prefix,
            $file,
            $written,
            $wadCount,
            $via,
            $skipped ? ', skipped ' . count($skipped) . ' unreadable names' : '',
        ));

        if ($wadCount === 0) {
            $this->warnProgress("{$prefix} {$file} contains no .wad file; wads:analyse will ignore it.");
            $this->tally('no wad inside');
        }

        $this->tally('extracted');
    }

    /**
     * Extract entry by entry with PHP's ZipArchive, cleaning up odd entry names.
     * Returns [files written, .wad count, entry names skipped as unreadable].
     */
    private function extractWithZipArchive(ZipArchive $zip, string $partialDir): array
    {
        $written = 0;
        $wadCount = 0;
        $skipped = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if ($entry === false || str_ends_with($entry, '/') || str_starts_with($entry, '__MACOSX/')) {
                continue;
            }

            $cleanPath = $this->cleanZipEntryPath($entry);

            if ($cleanPath === '') {
                $skipped[] = $entry;

                continue;
            }

            $this->writeEntry($zip, $entry, $partialDir . '/' . $cleanPath);
            $written++;

            if (str_ends_with(strtolower($cleanPath), '.wad')) {
                $wadCount++;
            }
        }

        return [$written, $wadCount, $skipped];
    }

    /**
     * @return list<string> names of compression methods in this zip that libzip can't decompress
     */
    private function unsupportedMethods(ZipArchive $zip): array
    {
        $unsupported = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $method = $zip->statIndex($i)['comp_method'] ?? ZipArchive::CM_STORE;

            if (!ZipArchive::isCompressionMethodSupported($method, false)) {
                $unsupported[$method] = $this->compressionMethodName($method);
            }
        }

        return array_values($unsupported);
    }

    /**
     * Extract a whole zip with Info-ZIP unzip. Returns [files written, .wad count].
     */
    private function extractWithUnzip(string $zipPath, string $partialDir): array
    {
        if (!$this->unzipBinary) {
            throw new RuntimeException('zip uses a legacy compression method and the unzip binary is not installed (sudo apt install unzip)');
        }

        if (!is_dir($partialDir) && !@mkdir($partialDir, 0755, true) && !is_dir($partialDir)) {
            throw new RuntimeException("could not create {$partialDir}: " . (error_get_last()['message'] ?? 'unknown error'));
        }

        // -o overwrite without prompting, -qq quiet. Exit 1 is "warnings only"
        // (e.g. backslash path separators in old DOS zips), which is fine.
        $result = Process::timeout(600)->run([$this->unzipBinary, '-o', '-qq', $zipPath, '-d', $partialDir]);

        if (!in_array($result->exitCode(), [0, 1], true)) {
            throw new RuntimeException(sprintf(
                'unzip exited with code %d: %s',
                $result->exitCode(),
                trim($result->errorOutput() ?: $result->output()) ?: 'no output',
            ));
        }

        File::deleteDirectory($partialDir . '/__MACOSX');

        $files = File::allFiles($partialDir);
        $wads = array_filter($files, fn ($f) => strtolower($f->getExtension()) === 'wad');

        return [count($files), count($wads)];
    }

    private function compressionMethodName(int $method): string
    {
        return match (true) {
            $method === 1 => 'Shrink',
            $method >= 2 && $method <= 5 => 'Reduce',
            $method === 6 => 'Implode',
            $method === 9 => 'Deflate64',
            default => "method {$method}",
        };
    }

    private function writeEntry(ZipArchive $zip, string $entry, string $destination): void
    {
        $dir = dirname($destination);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("could not create {$dir}: " . (error_get_last()['message'] ?? 'unknown error'));
        }

        $source = $zip->getStream($entry);

        if ($source === false) {
            throw new RuntimeException("could not read entry '{$entry}' from zip: " . $zip->getStatusString());
        }

        $target = @fopen($destination, 'w');

        if ($target === false) {
            fclose($source);

            throw new RuntimeException("could not write {$destination}: " . (error_get_last()['message'] ?? 'unknown error'));
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException("failed copying '{$entry}' (disk full?)");
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    private function cleanZipEntryPath(string $entry): string
    {
        // Remove control/invisible characters. Old zips often have non-UTF-8 (CP437)
        // names, where the /u regex fails and returns null, so fall back to ASCII.
        $clean = preg_replace('/[^\PC\s]/u', '', $entry)
            ?? preg_replace('/[\x00-\x1F\x7F-\xFF]/', '_', $entry);

        $clean = preg_replace('/[[:space:]]+/', ' ', $clean); // Collapse all whitespace
        $clean = str_replace(['\\', '..'], ['/', ''], trim($clean));

        return trim($clean, '/ ');
    }

    private function zipErrorName(int $code): string
    {
        return match ($code) {
            ZipArchive::ER_NOZIP => 'not a zip archive',
            ZipArchive::ER_INCONS => 'archive inconsistent',
            ZipArchive::ER_CRC => 'CRC error',
            ZipArchive::ER_OPEN => "can't open file",
            ZipArchive::ER_READ => 'read error',
            ZipArchive::ER_NOENT => 'no such file',
            ZipArchive::ER_MEMORY => 'out of memory',
            default => 'unknown',
        };
    }
}
