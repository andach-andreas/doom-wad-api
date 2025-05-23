<?php

// tests/Feature/ApiTest.php
namespace Tests\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Wad;

class WadApiTest extends TestCase
{
    use RefreshDatabase;

    public function testShow()
    {
        Wad::factory()->create(['filename' => 'doomstuff']);
        Wad::factory()->create(['filename' => 'notmatch']);

        $this->getJson('/api/v1/wad/doomstuff')
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['filename' => 'doomstuff']);
    }

    public function testShowFail()
    {
        Wad::factory()->create(['filename' => 'doomstuff']);
        Wad::factory()->create(['filename' => 'notmatch']);

        $this->getJson('/api/v1/wad/asdf')
            ->assertStatus(404)
            ->assertJsonCount(2)
            ->assertJsonFragment(['message' => 'WAD not found']);
    }
}
