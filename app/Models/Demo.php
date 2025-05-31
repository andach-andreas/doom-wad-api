<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Demo extends Model
{
    protected $table = 'demos';

    protected $primaryKey = 'id';
    public $incrementing = false; // Since the primary key is not auto-incrementing
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'map_id',
        'wad_id',
        'category',
        'player',
        'engine',
        'note',
        'time',
        'lmp_file',
        'lmp_url_zip',
        'youtube_id',
        'youtube_link',
        'comment',
        'version',
        'skill_number',
        'mode_number',
        'respawn',
        'fast',
        'nomonsters',
        'number_of_players',
        'tics',
        'seconds',
    ];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function wad()
    {
        return $this->belongsTo(Wad::class);
    }

    public function fetchAndExtractLmp()
    {
        if ($this->lmp_file && Storage::disk('demos')->exists($this->lmp_file)) {
            return $this->lmp_file;
        }

        $zipDisk = Storage::disk('demos_zips');
        $demoDisk = Storage::disk('demos');

        $remoteZipUrl = $this->lmp_url_zip;
        $zipFilename = basename(parse_url($remoteZipUrl, PHP_URL_PATH));
        $zipPath = $zipDisk->path($zipFilename);

        // Download ZIP if not already present
        if (!$zipDisk->exists($zipFilename)) {
            $response = Http::timeout(30)->get($remoteZipUrl);
            if (!$response->ok()) {
                throw new \Exception("Failed to download zip from: $remoteZipUrl");
            }
            $zipDisk->put($zipFilename, $response->body());
        }

        // Extract to a unique folder
        $extractFolder = "demo_{$this->id}";
        $extractPath = $demoDisk->path($extractFolder);

        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            throw new \Exception("Failed to open zip file: $zipPath");
        }

        $lmpFound = false;
        $txtFound = false;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());

                if (!$lmpFound && $ext === 'lmp') {
                    $relativePath = ltrim(str_replace($demoDisk->path(''), '', $file->getPathname()), DIRECTORY_SEPARATOR);
                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                    $this->lmp_file = $relativePath;
                    $lmpFound = true;
                }

                if (!$txtFound && $ext === 'txt') {
                    $this->lmp_text_file = file_get_contents($file->getPathname());
                    $txtFound = true;
                }

                if ($lmpFound && $txtFound) {
                    break;
                }
            }
        }

        if (!$lmpFound) {
            throw new \Exception("No .lmp file found in demo {$this->id}");
        }

        $this->save();

        return $this->lmp_file;
    }

}
