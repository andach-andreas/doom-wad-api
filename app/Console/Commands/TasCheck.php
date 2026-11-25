<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Wad;
use Illuminate\Support\Facades\Storage;

class TasCheck extends Command
{
    protected $signature = 'wads:tas-check';
    protected $description = 'Scan all WADs and run tasCheck() on each related Demo';

    public function handle()
    {
        $this->info('Loading WADs and demos...');
        $wads = Wad::with('demosLink')->get();
        $this->info("Loaded {$wads->count()} WADs.");

        foreach ($wads as $wad) {
            foreach ($wad->demosLink as $demo) {
                $demoPath = $demo->lmp_file;

                if (!Storage::disk('demos')->exists($demoPath)) {
                    $this->warn("Demo file missing: $demoPath");
                    continue;
                }

                $result = $demo->tasCheck();
                $this->info("Checked {$demoPath} => TAS? " . ($result ? 'Yes' : 'No'));
            }
        }

        $this->info('WAD demo scan complete.');
    }
}
