<?php

namespace App\Console\Commands;

use Andach\DoomWadAnalysis\DSDA;
use App\Models\Demo;
use App\Models\Map;
use App\Models\Wad;
use Illuminate\Console\Command;

class DSDASync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsda:sync {wadName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull DSDA demo records for a WAD (by folder name) and analyse their LMPs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wadName = $this->argument('wadName');

        $wad = Wad::where('foldername', $wadName)->first();

        if (!$wad) {
            $this->error("No WAD found with foldername [{$wadName}].");

            return self::FAILURE;
        }

        $dsda = new DSDA($wadName);

        $this->info("DSDA parsing initialized for: {$wadName}");

        $mapCache = Map::where('wad_id', $wad->id)
            ->pluck('id', 'internal_name')
            ->toArray();

        foreach ($dsda->records as $id => $data) {
            $this->info("Parsing Demo: {$id}");
            $data['id'] = $id;
            $data['wad_id'] = $wad->id;
            $data['map_id'] = $mapCache[$data['level']] ?? null;

            $demo = Demo::updateOrCreate(
                ['id' => $id],
                $data
            );
            $demo->fetchAndExtractLmp();
            $demo->analyseLmp();
        }
    }
}
