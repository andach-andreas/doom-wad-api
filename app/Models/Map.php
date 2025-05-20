<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Map extends Model
{
    use HasFactory;

    protected $table = 'maps';

    protected $fillable = [
        'wad_id',
        'internal_name',
        'name',
        'count_things',
        'count_linedefs',
        'count_sidedefs',
        'count_vertexes',
        'count_sectors',
    ];

    public function wad()
    {
        return $this->belongsTo(Wad::class);
    }
}
