<?php

namespace App\Console\Commands;

use App\Console\Concerns\ReportsProgress;
use App\Models\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TasCheck extends Command
{
    use ReportsProgress;

    protected $signature = 'wads:tas-check';
    protected $description = 'Scan all WADs and run tasCheck() on each related Demo';

    public function handle()
    {
        if (!$this->begin('TAS check demos', ['demos'])) {
            return self::FAILURE;
        }

        $total = Demo::whereNotNull('wad_id')->count();
        $this->progress("{$total} demos to check.");

        $i = 0;

        // lazyById streams demos in chunks rather than loading every WAD and demo at once.
        foreach (Demo::whereNotNull('wad_id')->lazyById(200) as $demo) {
            $prefix = sprintf('[%d/%d] demo %s', ++$i, $total, $demo->id);
            $demoPath = $demo->lmp_file;

            if (!$demoPath) {
                $this->warnProgress("{$prefix}: no LMP file recorded (run dsda:sync for its WAD)");
                $this->tally('no lmp');

                continue;
            }

            if (!Storage::disk('demos')->exists($demoPath)) {
                $this->warnProgress("{$prefix}: LMP file missing on disk: {$demoPath}");
                $this->tally('missing file');

                continue;
            }

            try {
                $isTas = $demo->tasCheck();
            } catch (Throwable $e) {
                $this->reportFailure("{$prefix}: TAS check failed for {$demoPath}", $e);
                $this->tally('failed');

                continue;
            }

            $this->progress("{$prefix} {$demoPath} => TAS? " . ($isTas ? 'YES' : 'no'));
            $this->tally($isTas ? 'tas' : 'clean');
        }

        return $this->finish();
    }
}
