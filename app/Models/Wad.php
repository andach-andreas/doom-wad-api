<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wad extends Model
{
    protected $fillable = [
        'type',
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
        'filename',
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

    protected $casts = [
        'maps' => 'array',
    ];

    public function maps()
    {
        return $this->hasMany(Map::class);
    }
}
