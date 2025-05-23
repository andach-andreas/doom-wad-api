<?php

namespace Database\Factories;

use App\Models\Wad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Map>
 */
class MapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wad_id' => Wad::factory(),
            'internal_name' => $this->faker->regexify('MAP[0-9]{2}'),
            'name' => $this->faker->words(3, true),
            'image_path' => null,
            'count_things' => $this->faker->numberBetween(100, 1000),
            'count_linedefs' => $this->faker->numberBetween(200, 2000),
            'count_sidedefs' => $this->faker->numberBetween(200, 2000),
            'count_vertexes' => $this->faker->numberBetween(100, 1500),
            'count_sectors' => $this->faker->numberBetween(10, 300),
        ];

    }
}
