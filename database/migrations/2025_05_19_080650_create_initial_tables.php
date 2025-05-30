<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demos', function (Blueprint $table) {
            $table->integer('id')->primary(); // Using third-party demo ID as primary key
            $table->string('map_id');
            $table->integer('wad_id');
            $table->string('category');
            $table->string('player');
            $table->string('engine');
            $table->string('note')->nullable();
            $table->string('time');
            $table->string('lmp_url');
            $table->string('youtube_id')->nullable();
            $table->string('youtube_link')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('maps', function (Blueprint $table) {
            $table->id();
            $table->integer('wad_id');
            $table->string('internal_name'); // e.g. E1M1, MAP01
            $table->text('name')->nullable(); // Human-readable map name
            $table->string('image_path')->nullable();
            $table->unsignedInteger('count_things')->default(0);
            $table->unsignedInteger('count_linedefs')->default(0);
            $table->unsignedInteger('count_sidedefs')->default(0);
            $table->unsignedInteger('count_vertexes')->default(0);
            $table->unsignedInteger('count_sectors')->default(0);
            $table->timestamps();

            $table->unique(['wad_id', 'internal_name']);
        });

        Schema::create('wads', function (Blueprint $table) {
            $table->id();

            $table->string('filename');
            $table->string('filename_with_extension');
            $table->string('idgames_path');
            $table->integer('complevel')->nullable();
            $table->integer('maps_count')->nullable();
            $table->integer('linedefs_count')->nullable();
            $table->integer('sidedefs_count')->nullable();
            $table->integer('vertexes_count')->nullable();
            $table->integer('textures_count')->nullable();
            $table->integer('things_count')->nullable();
            $table->integer('sectors_count')->nullable();
            $table->string('iwad')->nullable();

            // From Text File
            $table->text('archive_maintainer')->nullable();
            $table->text('update_to')->nullable();
            $table->text('advanced_engine_needed')->nullable();
            $table->text('primary_purpose')->nullable();
            $table->text('title')->nullable();
            $table->text('release_date')->nullable();
            $table->text('author')->nullable();
            $table->text('email_address')->nullable();
            $table->text('other_files_by_author')->nullable();
            $table->text('misc_author_info')->nullable();
            $table->text('description')->nullable();
            $table->text('credits')->nullable();
            $table->text('new_levels')->nullable();
            $table->text('sounds')->nullable();
            $table->text('music')->nullable();
            $table->text('graphics')->nullable();
            $table->text('dehacked_patch')->nullable();
            $table->text('demos')->nullable();
            $table->text('other')->nullable();
            $table->text('other_files_required')->nullable();
            $table->text('game')->nullable();
            $table->text('map')->nullable();
            $table->text('single_player')->nullable();
            $table->text('coop')->nullable();
            $table->text('deathmatch')->nullable();
            $table->text('other_game_styles')->nullable();
            $table->text('difficulty_settings')->nullable();
            $table->text('base')->nullable();
            $table->text('build_time')->nullable();
            $table->text('editors_used')->nullable();
            $table->text('known_bugs')->nullable();
            $table->text('may_not_run_with')->nullable();
            $table->text('tested_with')->nullable();
            $table->text('where_to_get_web')->nullable();
            $table->text('where_to_get_ftp')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('initial_tables');
    }
};
