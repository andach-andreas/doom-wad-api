<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class UnzipWads extends Command
{
    protected $signature = 'wads:unzip';
    protected $description = 'Unzip WAD zip files into appropriate directories using Laravel Storage disks';

    public function handle()
    {
        $mainDirs = [
            'doom' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom/Ports' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom/deathmatch' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom/deathmatch/Ports' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z'],
            'doom2' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom2/Ports' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom2/deathmatch' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z', 'megawads'],
            'doom2/deathmatch/Ports' => ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z'],
        ];

        foreach ($mainDirs as $main => $subDirs) {
            foreach ($subDirs as $sub) {
                $pathPrefix = "$main/$sub";
                $zipFiles = Storage::disk('zips')->files($pathPrefix);

                foreach ($zipFiles as $file) {
                    if (!str_ends_with($file, '.zip')) {
                        continue;
                    }

                    $baseName = pathinfo($file, PATHINFO_FILENAME);
                    $extractPath = "$pathPrefix/$baseName";

                    if (Storage::disk('wads')->exists($extractPath)) {
                        $this->info("Already extracted: $file");
                        continue;
                    }

                    $this->info("Extracting: $file");
                    $tempZip = tempnam(sys_get_temp_dir(), 'wadzip');
                    file_put_contents($tempZip, Storage::disk('zips')->get($file));

                    $zip = new ZipArchive();
                    if ($zip->open($tempZip) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry = $zip->getNameIndex($i);

                            // Skip directory entries (files only)
                            if (str_ends_with($entry, '/')) {
                                continue;
                            }

                            $stream = $zip->getStream($entry);
                            if ($stream) {
                                $contents = stream_get_contents($stream);
                                fclose($stream);

                                $cleanPath = $this->cleanZipEntryPath($entry);
                                $fullPath = "$extractPath/$cleanPath";

                                // Ensure parent directory exists
                                $localPath = Storage::disk('wads')->path(dirname($fullPath));
                                if (!is_dir($localPath)) {
                                    mkdir($localPath, 0777, true); // Create nested folders recursively
                                }

                                Storage::disk('wads')->put($fullPath, $contents);
                            }
                        }
                        $zip->close();
                        $this->info("Successfully extracted: $file");
                    } else {
                        $this->warn("Failed to open zip: $file");
                    }

                    @unlink($tempZip);
                }
            }
        }

        $this->info('All zip files processed.');
    }

    private function cleanZipEntryPath(string $entry): string
    {
        // Remove funky whitespace characters and normalize
        $entry = preg_replace('/[^\PC\s]/u', '', $entry); // Remove control/invisible characters
        $entry = preg_replace('/[[:space:]]+/', ' ', $entry); // Collapse all whitespace
        $entry = trim($entry); // Trim leading/trailing space

        return str_replace(['\\', '..'], ['/', ''], $entry);
    }
}
