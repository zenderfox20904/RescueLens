<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('auto_post_enabled')->default(false);
            $t->timestamp('auto_post_consent_at')->nullable();
        });
        Schema::create('camera_feeds', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('name', 120);
            $t->string('kind', 20)->default('snapshot_url'); // snapshot_url | upload
            $t->string('snapshot_url', 500)->nullable();
            $t->decimal('lat', 9, 6);
            $t->decimal('lng', 9, 6);
            $t->string('area_name', 120)->nullable();
            $t->char('country_code', 2)->default('BD');
            $t->unsignedInteger('interval_seconds')->default(300);
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_polled_at')->nullable();
            $t->string('last_error', 120)->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::table('incidents', function (Blueprint $t) {
            $t->string('source', 20)->default('user')->index(); // user | auto_post | camera
            $t->boolean('ai_unverified')->default(false);
            $t->decimal('ai_confidence', 4, 3)->nullable();
            $t->boolean('feed_eligible')->default(true)->index();
            $t->foreignId('camera_feed_id')->nullable()->constrained('camera_feeds')->nullOnDelete();
        });
        Schema::create('camera_frames', function (Blueprint $t) {
            $t->id();
            $t->foreignId('camera_feed_id')->constrained('camera_feeds')->cascadeOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('incident_media')->nullOnDelete();
            $t->string('status', 20)->default('queued'); // queued | analyzed | no_incident | duplicate | incident | failed
            $t->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $t->string('error', 120)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_frames');
        Schema::table('incidents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('camera_feed_id');
            $t->dropColumn(['source', 'ai_unverified', 'ai_confidence', 'feed_eligible']);
        });
        Schema::dropIfExists('camera_feeds');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['auto_post_enabled', 'auto_post_consent_at']));
    }
};
