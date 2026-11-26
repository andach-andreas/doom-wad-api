<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * wads:analyse upserted on `filename`, which had no unique index, so on MySQL every
 * run inserted a fresh copy of every WAD. A WAD is identified by its folder plus its
 * file name (one zip can hold several WADs, and the same .wad name appears in many
 * zips), so dedupe on that pair and enforce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('wads')
            ->select('idgames_path', 'filename', DB::raw('MIN(id) as keep_id'))
            ->groupBy('idgames_path', 'filename')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $removeIds = DB::table('wads')
                ->where('idgames_path', $group->idgames_path)
                ->where('filename', $group->filename)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            DB::transaction(function () use ($group, $removeIds) {
                // Lookups used ->first() (lowest id), so the kept row already owns the
                // maps and demos; move any strays across and drop map rows that would
                // clash with the kept row's unique (wad_id, internal_name).
                $keptMaps = DB::table('maps')->where('wad_id', $group->keep_id)->pluck('internal_name');

                DB::table('maps')
                    ->whereIn('wad_id', $removeIds)
                    ->whereIn('internal_name', $keptMaps)
                    ->delete();

                DB::table('maps')->whereIn('wad_id', $removeIds)->update(['wad_id' => $group->keep_id]);
                DB::table('demos')->whereIn('wad_id', $removeIds)->update(['wad_id' => $group->keep_id]);
                DB::table('wads')->whereIn('id', $removeIds)->delete();
            });
        }

        Schema::table('wads', function (Blueprint $table) {
            $table->unique(['idgames_path', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::table('wads', function (Blueprint $table) {
            $table->dropUnique(['idgames_path', 'filename']);
        });
    }
};
