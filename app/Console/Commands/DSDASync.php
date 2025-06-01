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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wadName = $this->argument('wadName');

        $dsda = new DSDA($wadName);

        // Optional: output confirmation
        $this->info("DSDA parsing initialized for: {$wadName}");

        $wad = Wad::where('filename', $wadName)->first();

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
