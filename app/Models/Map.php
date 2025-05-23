<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Map extends Model
{
    use HasFactory;

    protected $appends = ['image_url'];

    protected $table = 'maps';

    protected $fillable = [
        'wad_id',
        'internal_name',
        'name',
        'image_path',
        'count_things',
        'count_linedefs',
        'count_sidedefs',
        'count_vertexes',
        'count_sectors',
    ];

    public function demos()
    {
        return $this->hasMany(Demo::class);
    }

    public function wad()
    {
        return $this->belongsTo(Wad::class);
    }

    public function getImageUrlAttribute()
    {
        return url('storage/maps/' . $this->attributes['image_path']);
    }
}
