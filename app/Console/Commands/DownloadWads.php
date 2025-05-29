<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DownloadWads extends Command
{
    protected $signature = 'wads:download';
    protected $description = 'Download WAD ZIP files from idgames mirror';

    public function handle()
    {
        $baseUrl = 'https://youfailit.net/pub/idgames/levels/';
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
                $url = $baseUrl . "$main/$sub/";
                $this->info("Fetching from $url");
                $html = @file_get_contents($url);

                if (!$html) {
                    $this->warn("Failed to load $url");
                    continue;
                }

                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $dom->loadHTML($html);
                $links = $dom->getElementsByTagName('a');


                $client = new Client();

                foreach ($links as $link) {
                    $href = $link->getAttribute('href');

                    if (preg_match('/\.zip$/i', $href)) {
                        $fileUrl = $url . $href;
                        $relativePath = "$main/$sub/$href";
                        $fullPath = Storage::disk('zips')->path($relativePath);

                        if (!Storage::disk('zips')->exists($relativePath)) {
                            $this->info("Downloading $fileUrl");

                            // Ensure parent directory exists
                            if (!file_exists(dirname($fullPath))) {
                                mkdir(dirname($fullPath), 0755, true);
                            }

                            try {
                                $client->request('GET', $fileUrl, [
                                    'sink' => $fullPath,
                                    'timeout' => 60,
                                ]);
                            } catch (\Exception $e) {
                                $this->warn("Failed to download $fileUrl: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        $this->info("Download complete.");
    }
}
