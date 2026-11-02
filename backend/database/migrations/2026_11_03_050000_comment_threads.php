<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_comments', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->index();
            $t->timestamp('edited_at')->nullable();
            $t->unsignedInteger('likes_count')->default(0);
        });
        Schema::create('comment_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('comment_id')->index();
            $t->foreignId('user_id');
            $t->timestamps();
            $t->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_likes');
        Schema::table('incident_comments', fn (Blueprint $t) => $t->dropColumn(['parent_id', 'edited_at', 'likes_count']));
    }
};
