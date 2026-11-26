<?php

namespace App\Console\Commands;

use Andach\DoomWadAnalysis\DSDA;
use App\Console\Concerns\ReportsProgress;
use App\Models\Demo;
use App\Models\Map;
use App\Models\Wad;
use Illuminate\Console\Command;
use Throwable;

class DSDASync extends Command
{
    use ReportsProgress;

    protected $signature = 'dsda:sync {wadName}';
    protected $description = 'Pull DSDA demo records for a WAD (by folder name) and analyse their LMPs';

    public function handle()
    {
        $wadName = $this->argument('wadName');

        if (!$this->begin("DSDA sync: {$wadName}", ['demos', 'demos_zips'])) {
            return self::FAILURE;
        }

        $wad = Wad::where('foldername', $wadName)->first();

        if (!$wad) {
            $this->reportFailure("No WAD found with foldername [{$wadName}].");

            return self::FAILURE;
        }

        try {
            $dsda = new DSDA($wadName);
        } catch (Throwable $e) {
            $this->reportFailure("Could not load DSDA records for [{$wadName}]", $e);

            return self::FAILURE;
        }

        $records = $dsda->records;
        $total = count($records);
        $this->progress("WAD #{$wad->id} {$wad->filename_with_extension}: {$total} DSDA records.");

        $mapCache = Map::where('wad_id', $wad->id)
            ->pluck('id', 'internal_name')
            ->toArray();

        $i = 0;

        foreach ($records as $id => $data) {
            $prefix = sprintf('[%d/%d] demo %s (%s)', ++$i, $total, $id, $data['level'] ?? '?');

            try {
                $data['id'] = $id;
                $data['wad_id'] = $wad->id;
                $data['map_id'] = $mapCache[$data['level'] ?? ''] ?? null;

                $demo = Demo::updateOrCreate(['id' => $id], $data);
                $lmp = $demo->fetchAndExtractLmp();
                $demo->analyseLmp();
            } catch (Throwable $e) {
                $this->reportFailure("{$prefix}: failed", $e, ['zip_url' => $data['lmp_url_zip'] ?? null]);
                $this->tally('failed');

                continue;
            }

            $this->progress("{$prefix} OK | {$lmp}");
            $this->tally('synced');
        }

        return $this->finish();
    }
}
