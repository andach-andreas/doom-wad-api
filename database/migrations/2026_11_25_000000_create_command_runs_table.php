<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->json('arguments')->nullable();
            $table->string('status')->default('queued')->index(); // queued, running, done, failed
            $table->integer('exit_code')->nullable();
            $table->longText('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['command', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
