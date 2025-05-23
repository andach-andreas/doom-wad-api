<?php

namespace Tests\Api;

use App\Models\Map;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Wad;

class MapApiTest extends TestCase
{
    use RefreshDatabase;

    public function testShow()
    {
        $wad = Wad::factory()->create(['filename' => 'doomstuff']);
        $nonMatchingWad = Wad::factory()->create(['filename' => 'notmatch']);

        // Attach maps to both WADs
        Map::factory()->create([
            'wad_id' => $wad->id,
            'internal_name' => 'MAP01',
        ]);

        Map::factory()->create([
            'wad_id' => $nonMatchingWad->id,
            'internal_name' => 'MAP02',
        ]);

        $this->getJson('/api/v1/map/doomstuff/MAP01')
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['internal_name' => 'MAP01']);
    }
}
