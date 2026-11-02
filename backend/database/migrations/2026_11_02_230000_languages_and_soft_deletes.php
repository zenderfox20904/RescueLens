<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Worldwide languages, cached machine translations, and soft deletes for every admin managed entity. */
return new class extends Migration {
    private array $soft = ['users', 'incidents', 'incident_comments', 'emergency_resources', 'camera_feeds'];

    public function up(): void
    {
        Schema::create('languages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();
            $t->string('native_name', 80);
            $t->string('english_name', 80);
            $t->boolean('rtl')->default(false);
            $t->boolean('enabled')->default(true)->index();
            $t->boolean('hand_translated')->default(false);
            $t->unsignedSmallInteger('sort')->default(100);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('ui_translations', function (Blueprint $t) {
            $t->id();
            $t->string('lang', 12);
            $t->string('key', 120);
            $t->char('source_hash', 40);
            $t->text('value');
            $t->string('provider', 60);
            $t->timestamps();
            $t->unique(['lang', 'key']);
        });
        foreach ($this->soft as $tb) Schema::table($tb, fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        foreach ($this->soft as $tb) Schema::table($tb, fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::dropIfExists('ui_translations');
        Schema::dropIfExists('languages');
    }
};
