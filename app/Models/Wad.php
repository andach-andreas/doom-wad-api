<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Wad extends Model
{
    use HasFactory;

    protected $fillable = [
        'foldername',
        'filename',
        'filename_with_extension',
        'idgames_path',
        'complevel',
        'levels_count',
        'linedefs_count',
        'sidedefs_count',
        'things_count',
        'sectors_count',
        'vertexes_count',
        'iwad',

        'archive_maintainer',
        'update_to',
        'advanced_engine_needed',
        'primary_purpose',
        'title',
        'release_date',
        'author',
        'email_address',
        'other_files_by_author',
        'misc_author_info',
        'description',
        'credits',
        'new_levels',
        'sounds',
        'music',
        'graphics',
        'dehacked_patch',
        'demos',
        'other',
        'other_files_required',
        'game',
        'map',
        'single_player',
        'coop',
        'deathmatch',
        'other_game_styles',
        'difficulty_settings',
        'base',
        'build_time',
        'editors_used',
        'known_bugs',
        'may_not_run_with',
        'tested_with',
        'where_to_get_web',
        'where_to_get_ftp',
    ];

    public function demos()
    {
        return $this->hasMany(Demo::class);
    }

    public function maps()
    {
        return $this->hasMany(Map::class);
    }

    public function updateDemos(): array
    {
        $url = 'https://dsdarchive.com/wads/' . urlencode($this->filename);
        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \Exception('WAD not found on DSDA.');
        }

        $html = $response->body();
        $records = [];
        $levelName = '';
        $categoryName = '';
        $count = 0;

        preg_match_all('/<tr.*?>(.*?)<\/tr>/is', $html, $trMatches);

        foreach ($trMatches[1] as $trArray) {
            $tds = $this->extractTds($trArray);
            $countTds = count($tds);

            if (!$countTds) continue;
            if ($countTds === 1) {
                $records[($count - 1)]['comment'] = $this->cleanTdContent($tds[0]);
                continue;
            }

            if ($countTds === 7) {
                $levelName = $this->cleanTdContent($tds[0]);
                $categoryName = $this->cleanTdContent($tds[1]);
            }

            if ($countTds === 6) {
                $categoryName = $this->cleanTdContent($tds[0]);
            }

            $demoId = (int) $this->extractDemoId($tds[$countTds - 2]);
            $mapID = $this->maps()->where('internal_name', $levelName)->first()->id;

            $record = [
                'id' => $demoId,
                'map_id' => $mapID,
                'category' => $categoryName,
                'player' => $this->cleanTdContent($tds[$countTds - 5]),
                'engine' => $this->cleanTdContent($tds[$countTds - 4]),
                'note' => $this->extractNote($tds[$countTds - 3]),
                'time' => $this->cleanTdContent($tds[$countTds - 2]),
                'lmp_url' => $this->extractWadURL($tds[$countTds - 2]),
                'youtube_id' => $this->extractYoutubeId($tds[$countTds - 1]),
                'youtube_link' => $this->extractYoutubeLink($tds[$countTds - 1]),
                'comment' => $records[$count]['comment'] ?? null,
                'wad_id' => $this->id,
            ];

            Demo::updateOrCreate(['id' => $demoId], $record);

            // Download LMP file
            if (!empty($record['lmp_url'])) {
                $path = "{$this->idgames_path}/{$levelName}/{$demoId}.lmp";
                $lmpContent = Http::get($record['lmp_url']);
                if ($lmpContent->successful()) {
                    Storage::disk('demos')->put($path, $lmpContent->body());
                }
            }

            $records[] = $record;
            $count++;
        }

        return $records;
    }

    private function cleanTdContent(string $tdHtml): string
    {
        return trim(strip_tags($tdHtml));
    }

    private function extractTds(string $trHtml): array
    {
        preg_match_all('/<td.*?>(.*?)<\/td>/is', $trHtml, $tdMatches);
        return $tdMatches[0];
    }

    private function extractDemoId(string $tdHtml): string
    {
        if (preg_match('/href="[^"]*\/(\d+)\//', $tdHtml, $match)) {
            return $match[1];
        }
        return '';
    }

    private function extractNote(string $tdHtml): string
    {
        if (preg_match('/aria-label="([^"]+)"/i', $tdHtml, $match)) {
            return trim($match[1]);
        }
        return trim(strip_tags($tdHtml));
    }

    private function extractWadURL(string $tdHtml): string
    {
        if (preg_match('/href="(\/files\/[^"]+)"/i', $tdHtml, $match)) {
            return 'https://dsdarchive.com' . $match[1];
        }
        return '';
    }

    private function extractYoutubeId(string $tdHtml): string
    {
        if (preg_match('/href="https:\/\/www\.youtube\.com\/watch\?v=([^"&]+)"/i', $tdHtml, $match)) {
            return $match[1];
        }
        return '';
    }

    private function extractYoutubeLink(string $tdHtml): string
    {
        if (preg_match('/href="(https:\/\/www\.youtube\.com\/watch\?v=[^"]+)"/i', $tdHtml, $match)) {
            return $match[1];
        }
        return '';
    }

}
