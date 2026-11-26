<?php

namespace App\Console\Commands;

use Andach\DoomWadAnalysis\WadFile;
use App\Console\Concerns\ReportsProgress;
use App\Models\Map;
use App\Models\Wad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
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

        foreach ($wadFiles as $i => $filePath) {
            $idgamesPath = dirname($filePath);
            $fullPath = $disk->path($filePath);
            $logPrefix = sprintf('[%d/%d] %s', $i + 1, $total, $filePath);

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
