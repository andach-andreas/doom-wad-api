<?php

namespace App\Models;

use Andach\DoomWadAnalysis\Demo as ApiDemo;
use DirectoryIterator;
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

    public function analyseLmp()
    {
        if (!$this->lmp_file) {
            return null;
        }

        $path = Storage::disk('demos')->path($this->lmp_file);

        if (!file_exists($path)) {
            return null;
        }

        $apiDemo = new ApiDemo($path);
        $apiDemo->lmpStats();

        $this->update($apiDemo->stats);
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

        $iterator = new DirectoryIterator($extractPath);

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
                    $content = @file_get_contents($file->getPathname()) ?: '';
                    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $content);

                    $this->lmp_text_file = $clean !== false ? $clean : '';

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

    /**
     * Parses the .lmp binary demo file into an array of per-tic frame data.
     *
     * Each frame represents one tic (1/35th of a second) and contains input values
     * and action flags used during that tic. Useful for TAS analysis.
     *
     *     forward: int,             // Forward/backward movement (-128 to 127); positive is forward
     *     strafe: int,              // Left/right strafing (-128 to 127); positive is right
     *     turn: int,                // Turning angle (signed byte); positive is left
     *     fired: bool,              // Whether the fire button was pressed
     *     used: bool,               // Whether the use/open button was pressed
     *     weapon_change: int|null,  // Weapon number (1–8), or null if no change
     *     is_pause: bool,           // True if tic triggered pause/unpause (special mode)
     *     is_save: bool             // True if tic triggered game save (special mode)
     */
    public function parseFrames(): array
    {
        $binary = Storage::disk('demos')->get($this->lmp_file);
        $frames = [];

        $offset = 0;

        // Minimum header size: 13 bytes (1.2+ demos)
        $headerSize = 13;
        $offset += $headerSize;

        $length = strlen($binary);

        while ($offset + 4 <= $length) {
            $forward = unpack('c', $binary[$offset])[1];         // signed byte
            $strafe  = unpack('c', $binary[$offset + 1])[1];     // signed byte
            $turn    = unpack('c', $binary[$offset + 2])[1];     // signed byte (can be higher precision in Boom/MBF)
            $action  = ord($binary[$offset + 3]);                // unsigned byte

            // Interpret action bits
            $is_special     = ($action & 0b10000000) !== 0;
            $is_pause       = $is_special && ($action & 0b00000011) === 0b01;
            $is_save        = $is_special && ($action & 0b00000011) === 0b10;
            $weapon_change  = (!$is_special && ($action & 0b00000100)) ? (($action >> 3) & 0b111) + 1 : null;
            $used           = (!$is_special && ($action & 0b00000010)) !== 0;
            $fired          = (!$is_special && ($action & 0b00000001)) !== 0;

            $frames[] = [
                'forward'       => $forward,
                'strafe'        => $strafe,
                'turn'          => $turn,
                'fired'         => $fired,
                'used'          => $used,
                'weapon_change' => $weapon_change,
                'is_pause'      => $is_pause,
                'is_save'       => $is_save,
                'is_sr40'       => $forward !== 0 && $strafe !== 0 && $turn !== 0,
                'is_sr50'       => $forward !== 0 && $strafe !== 0 && $turn === 0,
            ];

            // Stop parsing if the marker byte is encountered
            if ($action === 0x80) {
                break;
            }

            $offset += 4;
        }

        return $frames;
    }


    public function tasCheck(): bool
    {
        $frames = $this->parseFrames(); // Assumes this returns an array of frame data

        return $this->tasCheckSR50Only($frames)
            || $this->tasCheckSR50Pause($frames)
            || $this->tasCheckPerfectTurns($frames);
    }

    public function tasCheckPerfectTurns(array $frames): bool
    {
        $threshold = 10; // Threshold for "too many" perfect turns, adjust as needed
        $count = 0;
        $tolerance = 3; // Allowable deviation from exact multiple of 45

        foreach ($frames as $frame) {
            $turn = $frame['turn'];

            // Ignore zero turn frames
            if ($turn === 0) {
                continue;
            }

            // Check if turn is close to a multiple of 45 degrees
            $mod = abs($turn) % 45;
            if ($mod <= $tolerance || $mod >= (45 - $tolerance)) {
                $count++;
            }
        }

        return $count >= $threshold;
    }


    public function tasCheckSR50Only(array $frames): bool
    {
        $hasSr50 = false;

        foreach ($frames as $frame) {
            if ($frame['is_sr40']) {
                // SR40 is present — not TAS
                return false;
            }

            if ($frame['is_sr50']) {
                $hasSr50 = true;
            }
        }

        return $hasSr50; // True only if at least one SR50 and zero SR40
    }

    public function tasCheckSR50Pause(array $frames): bool
    {
        $count = count($frames);

        for ($i = 0; $i < $count - 2; $i++) {
            $first  = $frames[$i];
            $second = $frames[$i + 1];
            $third  = $frames[$i + 2];

            if (
                $first['is_sr50'] &&
                $second['forward'] === 0 && $second['strafe'] === 0 &&
                $third['is_sr50']
            ) {
                return true;
            }
        }

        return false;
    }

}
