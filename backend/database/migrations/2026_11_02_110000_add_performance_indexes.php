<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $t) {
            $t->index(['status', 'merged_into_id', 'id'], 'incidents_feed_idx');
            $t->index(['category', 'created_at'], 'incidents_cat_created_idx');
            $t->index(['user_id', 'id'], 'incidents_user_idx');
        });
        Schema::table('incident_comments', fn (Blueprint $t) => $t->index(['incident_id', 'id'], 'comments_incident_idx'));
        Schema::table('user_notifications', fn (Blueprint $t) => $t->index(['user_id', 'id'], 'notif_user_idx'));
        Schema::table('incident_media', fn (Blueprint $t) => $t->string('thumb_path')->nullable()->after('path'));
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $t) {
            $t->dropIndex('incidents_feed_idx'); $t->dropIndex('incidents_cat_created_idx'); $t->dropIndex('incidents_user_idx');
        });
        Schema::table('incident_comments', fn (Blueprint $t) => $t->dropIndex('comments_incident_idx'));
        Schema::table('user_notifications', fn (Blueprint $t) => $t->dropIndex('notif_user_idx'));
        Schema::table('incident_media', fn (Blueprint $t) => $t->dropColumn('thumb_path'));
    }
};
