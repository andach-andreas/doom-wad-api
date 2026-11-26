<?php

namespace App\Console\Commands;

use Andach\DoomWadAnalysis\WadFile;
use App\Console\Concerns\ReportsProgress;
use App\Models\Map;
use App\Models\Wad;
use App\Support\Idgames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Andach\DoomWadAnalysis\WadAnalyser;
use Andach\DoomWadAnalysis\TextFile;

class AnalyseWads extends Command
{
    use ReportsProgress;

    protected $signature = 'wads:analyse';
    protected $description = 'Run WadAnalyser on all WAD files in storage and parse associated text file';

    public function handle()
    {
        if (!$this->begin('Analyse WADs', ['wads'])) {
            return self::FAILURE;
        }

        $disk = Storage::disk('wads');
        $this->checkpoint('Scanning WAD directory (this can take a while)...');
        $wadFiles = array_values($this->getAllWadFiles($disk->allFiles()));
        $total = count($wadFiles);
        $this->checkpoint("Found {$total} WAD files.");

        $analyser = new WadAnalyser([
            'maps' => [
                'counts' => true,
//                'images' => true,
            ],
        ]);

        $deletedFolders = [];

        foreach ($wadFiles as $i => $filePath) {
            $idgamesPath = dirname($filePath);
            $fullPath = $disk->path($filePath);
            $logPrefix = sprintf('[%d/%d] %s', $i + 1, $total, $filePath);

            foreach ($deletedFolders as $folder) {
                if (str_starts_with($filePath, $folder . '/')) {
                    continue 2; // its folder was deleted for re-extraction earlier in this run
                }
            }

            // The parser library assumes a well-formed WAD and dies with an unpack()
            // error on a short file, so check the structure first.
            if ($problem = $this->wadProblem($fullPath)) {
                if ($folder = $this->handleBadWad($filePath, $fullPath, $problem, $logPrefix)) {
                    $deletedFolders[] = $folder;
                }

                continue;
            }

            try {
                $analysis = $analyser->analyse($fullPath);

                $wadDir = dirname($fullPath);
                $textFilePath = $this->findTextFile($wadDir);
                $textData = [];

                if ($textFilePath) {
                    $textFile = new TextFile($textFilePath);
                    $textData = $textFile->parse();
                }

                $insert = $this->mergeBothArrays($analysis, $textData);
                $insert['filename'] = strtolower(pathinfo($fullPath, PATHINFO_FILENAME));
                $insert['filename_with_extension'] = strtolower(basename($fullPath));
                $insert['idgames_path'] = $idgamesPath;
                $insert['foldername'] = basename($idgamesPath);
                $insert['iwad'] = $this->iwadFromPath($fullPath);
                // Unique on (idgames_path, filename): one zip can hold several WADs, and
                // the same .wad name turns up in many different zips.
                Wad::upsert($insert, ['idgames_path', 'filename']);
                $wad = Wad::where('idgames_path', $insert['idgames_path'])
                    ->where('filename', $insert['filename'])
                    ->firstOrFail();

                $mapInserts = [];
                foreach ($analysis['maps'] ?? [] as $internalMapName => $mapArray)
                {
                    if (!empty($mapArray['image'])) {
                        $imagePath = $this->storeMapImage($filePath, $internalMapName, $mapArray['image']);
                    } else {
                        $imagePath = null;
                    }

                    $mapInserts[] = [
                        'wad_id' => $wad->id,
                        'internal_name' => $internalMapName ?? '',
                        'name' => $mapArray['name'] ?? '',
                        'image_path' => $imagePath,
                        'count_things' => $mapArray['counts']['things'] ?? 0,
                        'count_linedefs' => $mapArray['counts']['linedefs'] ?? 0,
                        'count_sidedefs' => $mapArray['counts']['sidedefs'] ?? 0,
                        'count_vertexes' => $mapArray['counts']['vertexes'] ?? 0,
                        'count_sectors' => $mapArray['counts']['sectors'] ?? 0,
                    ];
                }

                Map::upsert(
                    $mapInserts,
                    ['wad_id', 'internal_name'],
                    ['name', 'image_path', 'count_things', 'count_linedefs', 'count_sidedefs', 'count_vertexes', 'count_sectors']
                );

                $this->progress(sprintf(
                    '%s OK | %d maps | complevel %s | text file: %s',
                    $logPrefix,
                    count($mapInserts),
                    $insert['complevel'] ?? '?',
                    $textFilePath ? basename($textFilePath) : 'none',
                ));
                $this->tally('analysed');
            } catch (\Throwable $e) {
                $this->reportFailure("{$logPrefix} analysis failed", $e, ['path' => $fullPath]);
                $this->tally('failed');
            }
        }

        return $this->finish();
    }


    protected function getAllWadFiles(array $allFiles): array
    {
        return array_filter($allFiles, fn($file) => str_ends_with(strtolower($file), '.wad')
            && !str_contains($file, '__MACOSX')
            && !str_contains($file, '.__partial/')); // still being extracted by wads:unzip
    }

    /**
     * Cheap structural check of a WAD: header type, and that the lump directory
     * fits inside the file. Returns a description of the problem, or null if OK.
     */
    protected function wadProblem(string $fullPath): ?string
    {
        $size = @filesize($fullPath);

        if ($size === false) {
            return 'file cannot be read';
        }

        if ($size < 12) {
            return "file is only {$size} bytes";
        }

        $handle = @fopen($fullPath, 'rb');

        if (!$handle) {
            return 'file cannot be opened: ' . (error_get_last()['message'] ?? 'unknown error');
        }

        $header = fread($handle, 12);
        fclose($handle);

        $type = substr($header, 0, 4);

        if ($type !== 'IWAD' && $type !== 'PWAD') {
            return 'not a WAD (header ' . bin2hex($type) . ')';
        }

        ['count' => $count, 'offset' => $offset] = unpack('Vcount/Voffset', $header, 4);
        $directoryEnd = $offset + $count * 16;

        if ($directoryEnd > $size) {
            return sprintf(
                'lump directory (%d lumps at byte %d) runs to byte %d but the file is only %d bytes',
                $count,
                $offset,
                $directoryEnd,
                $size,
            );
        }

        return null;
    }

    /**
     * Compare a bad WAD against its entry in the original zip. If the extracted copy
     * is a different size it was cut short (e.g. the disk filled mid-extraction), so
     * delete the extracted folder for wads:unzip to redo. If it matches, the upload
     * itself is broken and there's nothing to fix.
     *
     * @return string|null the wads-relative folder deleted, if any
     */
    protected function handleBadWad(string $filePath, string $fullPath, string $problem, string $logPrefix): ?string
    {
        [$folder, $zipFile] = $this->extractionSource($filePath);
        $zipPath = $zipFile ? Storage::disk('zips')->path($zipFile) : null;
        $expected = $zipPath ? $this->zipEntrySize($zipPath, basename($filePath)) : null;
        $actual = @filesize($fullPath);

        if ($expected !== null && $expected !== $actual) {
            File::deleteDirectory(Storage::disk('wads')->path($folder));
            $this->warnProgress(sprintf(
                '%s TRUNCATED (%s on disk, %s in %s): deleted %s, re-run wads:unzip then wads:analyse',
                $logPrefix,
                $this->humanBytes($actual),
                $this->humanBytes($expected),
                $zipFile,
                $folder,
            ));
            $this->tally('truncated, deleted for re-extract');

            return $folder;
        }

        $why = $expected === null
            ? ($zipPath ? "couldn't compare with {$zipFile}" : 'source zip not found')
            : 'same size as in the zip, so the original upload is broken';

        $this->warnProgress("{$logPrefix} SKIPPED invalid WAD: {$problem} ({$why})");
        $this->tally('invalid wad');

        return null;
    }

    /**
     * Map "doom/0-9/10years/sub/x.wad" to ["doom/0-9/10years", "doom/0-9/10years.zip"].
     *
     * @return array{0: string, 1: ?string}
     */
    protected function extractionSource(string $filePath): array
    {
        $best = null;

        foreach (Idgames::directories() as $dir) {
            if (str_starts_with($filePath, $dir . '/') && strlen($dir) > strlen($best ?? '')) {
                $best = $dir;
            }
        }

        if ($best === null) {
            return [dirname($filePath), null];
        }

        $name = explode('/', substr($filePath, strlen($best) + 1))[0];
        $zipFile = "{$best}/{$name}.zip";

        return ["{$best}/{$name}", Storage::disk('zips')->exists($zipFile) ? $zipFile : null];
    }

    protected function zipEntrySize(string $zipPath, string $wadName): ?int
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return null;
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = basename(str_replace('\\', '/', $stat['name'] ?? ''));

                if (strcasecmp($entryName, $wadName) === 0) {
                    return (int) $stat['size'];
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    protected function findTextFile(string $directory): ?string
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $textFiles = [];

        foreach ($rii as $file) {
            if ($file->isFile() && preg_match('/\.txt$/i', $file->getFilename())) {
                $textFiles[] = $file->getPathname();
            }
        }

        if (count($textFiles) === 0) {
            return null;
        }

        if (count($textFiles) === 1) {
            return $textFiles[0];
        }

        foreach ($textFiles as $file) {
            if (stripos(file_get_contents($file), 'doom') !== false) {
                return $file;
            }
        }

        return $textFiles[0]; // fallback
    }

    protected function iwadFromPath(string $textFilePath): string
    {
        $normalizedPath = strtolower(str_replace(['\\', '/'], '/', $textFilePath));

        if (preg_match('#/(doom2)(/|$)#', $normalizedPath)) {
            return 'doom2';
        }

        if (preg_match('#/(doom)(/|$)#', $normalizedPath)) {
            return 'doom';
        }

        return '';
    }

    protected function mergeBothArrays(array $analysis, array $textData): array
    {
        $return = $textData;

        $return['complevel'] = $analysis['complevel'];
        $return['maps_count'] = $analysis['counts']['maps'];
        $return['things_count'] = $analysis['counts']['things'];
        $return['linedefs_count'] = $analysis['counts']['linedefs'];
        $return['sidedefs_count'] = $analysis['counts']['sidedefs'];
        $return['vertexes_count'] = $analysis['counts']['vertexes'];
        $return['sectors_count'] = $analysis['counts']['sectors'];

        return $return;
    }

    protected function storeMapImage(string $filePath, string $internalMapName, string $imageData): string
    {
        $relativeDir = dirname($filePath); // Get directory portion only
        $path = "{$relativeDir}/{$internalMapName}.png";

        Storage::disk('maps')->put($path, $imageData);

        return $path;
    }

}
