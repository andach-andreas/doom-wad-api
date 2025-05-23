<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'lmp_url',
        'youtube_id',
        'youtube_link',
        'comment',
    ];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function wad()
    {
        return $this->belongsTo(Wad::class);
    }
}
