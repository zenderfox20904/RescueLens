<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_media', function (Blueprint $t) {
            $t->string('video_path')->nullable()->after('path');
            $t->unsignedInteger('video_seconds')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('incident_media', fn (Blueprint $t) => $t->dropColumn(['video_path', 'video_seconds']));
    }
};
