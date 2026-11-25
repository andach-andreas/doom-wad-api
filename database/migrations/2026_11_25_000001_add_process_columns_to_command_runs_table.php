<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_runs', function (Blueprint $table) {
            $table->unsignedInteger('pid')->nullable()->after('exit_code');
            $table->string('queue_job_id')->nullable()->after('pid');
        });
    }

    public function down(): void
    {
        Schema::table('command_runs', function (Blueprint $table) {
            $table->dropColumn(['pid', 'queue_job_id']);
        });
    }
};
