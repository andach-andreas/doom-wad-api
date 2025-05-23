<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wad>
 */
class WadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => $this->faker->unique()->word(),
            'filename_with_extension' => $this->faker->unique()->word() . '.wad',
            'complevel' => $this->faker->numberBetween(1, 21),
            'maps_count' => $this->faker->numberBetween(1, 50),
            'linedefs_count' => $this->faker->numberBetween(100, 5000),
            'sidedefs_count' => $this->faker->numberBetween(100, 5000),
            'vertexes_count' => $this->faker->numberBetween(100, 5000),
            'textures_count' => $this->faker->numberBetween(10, 500),
            'things_count' => $this->faker->numberBetween(100, 1000),
            'sectors_count' => $this->faker->numberBetween(10, 500),
            'iwad' => $this->faker->randomElement(['doom', 'doom2']),
            'archive_maintainer' => $this->faker->name,
            'update_to' => null,
            'advanced_engine_needed' => $this->faker->word(),
            'primary_purpose' => 'Single Player',
            'title' => $this->faker->sentence(3),
            'release_date' => $this->faker->date(),
            'author' => $this->faker->name,
            'email_address' => $this->faker->email,
            'other_files_by_author' => null,
            'misc_author_info' => null,
            'description' => $this->faker->paragraph(),
            'credits' => $this->faker->sentence(),
            'new_levels' => 'Yes',
            'sounds' => 'Yes',
            'music' => 'Yes',
            'graphics' => 'Yes',
            'dehacked_patch' => 'No',
            'demos' => 'No',
            'other' => null,
            'other_files_required' => null,
            'game' => 'Doom II',
            'map' => null,
            'single_player' => 'Yes',
            'coop' => 'Yes',
            'deathmatch' => 'No',
            'other_game_styles' => null,
            'difficulty_settings' => 'Yes',
            'base' => 'New from scratch',
            'build_time' => '2 weeks',
            'editors_used' => 'Doom Builder',
            'known_bugs' => 'None',
            'may_not_run_with' => null,
            'tested_with' => 'GZDoom',
            'where_to_get_web' => 'https://example.com',
            'where_to_get_ftp' => null,
        ];

    }
}
