<?php

namespace App\Console\Commands;

use App\Models\Wad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Andach\DoomWadAnalysis\WadAnalyser;
use Andach\DoomWadAnalysis\TextFile;
use Illuminate\Support\Facades\Log;

class AnalyseWads extends Command
{
    protected $signature = 'wads:analyse';
    protected $description = 'Run WadAnalyser on all WAD files in storage and parse associated text file';

    public function handle()
    {
        $disk = Storage::disk('wads');
        $wadFiles = $this->getAllWadFiles($disk->allFiles());

        $analyser = new WadAnalyser([
            'maps' => [
                'counts' => true,
            ],
        ]);

        foreach ($wadFiles as $filePath) {
            if (str_contains($filePath, '__MACOSX')) {
                continue;
            }

            $fullPath = $disk->path($filePath);
            $logPrefix = "[WAD: $filePath]";

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
                $insert['iwad'] = $this->iwadFromPath($fullPath);
                Wad::upsert($insert, ['filename']);

                $message = "$logPrefix OK";
                $this->info($message);
            } catch (\Throwable $e) {
                $error = "$logPrefix Failed: " . $e->getMessage();
                $this->error($error);
                Log::error("$logPrefix Failed", [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('All WADs analysed.');
        Log::info('All WADs analysed.');
    }

    protected function getAllWadFiles(array $allFiles): array
    {
        return array_filter($allFiles, fn($file) => str_ends_with(strtolower($file), '.wad'));
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
}
