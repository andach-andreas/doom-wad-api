<?php

namespace App\Console\Commands;

use App\Console\Concerns\ReportsProgress;
use App\Support\Idgames;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class DownloadWads extends Command
{
    use ReportsProgress;

    protected $signature = 'wads:download';
    protected $description = 'Download WAD ZIP files from idgames mirror';

    public function handle()
    {
        if (!$this->begin('Download WADs', ['zips'])) {
            return self::FAILURE;
        }

        $disk = Storage::disk('zips');
        $client = new Client([
            'connect_timeout' => 30,
            'headers' => ['User-Agent' => 'doom-wad-api (idgames mirror sync)'],
        ]);

        $directories = Idgames::directories();
        $this->progress('Mirror: ' . Idgames::MIRROR_URL . ' | ' . count($directories) . ' directories');

        foreach ($directories as $d => $dir) {
            $url = Idgames::MIRROR_URL . "{$dir}/";
            $dirLabel = sprintf('[dir %d/%d] %s', $d + 1, count($directories), $dir);

            try {
                $html = (string) $client->get($url, ['timeout' => 60])->getBody();
            } catch (Throwable $e) {
                $this->reportFailure("{$dirLabel}: could not load listing {$url}", $e);
                $this->tally('failed');

                continue;
            }

            $zips = $this->zipLinks($html);
            $pending = array_values(array_filter($zips, fn ($href) => !$disk->exists("{$dir}/{$href}")));

            $this->progress(sprintf(
                '%s: %d zips listed, %d already downloaded, %d to download',
                $dirLabel,
                count($zips),
                count($zips) - count($pending),
                count($pending),
            ));
            $this->tally('already downloaded', count($zips) - count($pending));

            if ($pending && !$this->ensureDirectory($disk->path($dir))) {
                $this->tally('failed', count($pending));

                continue;
            }

            foreach ($pending as $i => $href) {
                $this->downloadOne($client, $url . $href, "{$dir}/{$href}", sprintf('  [%d/%d]', $i + 1, count($pending)));
            }
        }

        return $this->finish();
    }

    private function downloadOne(Client $client, string $fileUrl, string $relativePath, string $prefix): void
    {
        $fullPath = Storage::disk('zips')->path($relativePath);

        // Download to a .part file and only rename once complete, so an interrupted
        // run (kill, timeout, network drop) never leaves a truncated zip that later
        // runs would treat as already downloaded.
        $partPath = $fullPath . '.part';
        $started = microtime(true);

        try {
            $client->get($fileUrl, ['sink' => $partPath, 'timeout' => 300]);

            $zip = new ZipArchive();
            $openResult = $zip->open($partPath);

            if ($openResult !== true) {
                throw new \RuntimeException("downloaded file is not a valid zip (ZipArchive error {$openResult})");
            }

            $zip->close();

            if (!rename($partPath, $fullPath)) {
                throw new \RuntimeException('could not rename ' . basename($partPath) . ': ' . (error_get_last()['message'] ?? 'unknown error'));
            }
        } catch (Throwable $e) {
            @unlink($partPath);
            $this->reportFailure("{$prefix} {$relativePath}: download failed", $e, ['url' => $fileUrl]);
            $this->tally('failed');

            return;
        }

        $this->progress(sprintf(
            '%s Downloaded %s (%s in %.1fs)',
            $prefix,
            $relativePath,
            $this->humanBytes(filesize($fullPath)),
            microtime(true) - $started,
        ));
        $this->tally('downloaded');
    }

    /**
     * @return list<string> zip hrefs from an Apache/nginx directory listing
     */
    private function zipLinks(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $hrefs = [];

        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');

            if (preg_match('/\.zip$/i', $href)) {
                $hrefs[] = $href;
            }
        }

        return array_values(array_unique($hrefs));
    }

    private function ensureDirectory(string $path): bool
    {
        if (is_dir($path) || @mkdir($path, 0755, true) || is_dir($path)) {
            return true;
        }

        $this->reportFailure("Could not create directory {$path}: " . (error_get_last()['message'] ?? 'unknown error'));

        return false;
    }
}
