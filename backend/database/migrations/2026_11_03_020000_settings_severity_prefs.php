<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->text('value')->nullable();
            $t->boolean('is_secret')->default(false);
            $t->timestamps();
        });
        Schema::create('auth_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 20); // password_reset | email_verify
            $t->string('token_hash', 64)->nullable();
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'purpose']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->json('notify_prefs')->nullable();
            $t->string('theme', 10)->default('system');
        });
        Schema::table('incidents', function (Blueprint $t) {
            $t->float('severity_confidence')->nullable();
            $t->json('severity_reasons')->nullable();
            $t->string('severity_source', 12)->default('ai');
            $t->json('recommended_responders')->nullable();
            $t->unsignedSmallInteger('alert_radius_km')->nullable();
            $t->string('source_lang', 8)->nullable();
        });
        Schema::create('content_translations', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type', 20); // incident | comment
            $t->unsignedBigInteger('subject_id');
            $t->string('field', 20);
            $t->string('locale', 8);
            $t->string('source_lang', 8)->nullable();
            $t->string('source_hash', 40);
            $t->text('text');
            $t->timestamps();
            $t->unique(['subject_type', 'subject_id', 'field', 'locale'], 'ct_unique');
        });
        Schema::table('incident_comments', fn (Blueprint $t) => $t->string('source_lang', 8)->nullable());
    }

    public function down(): void
    {
        Schema::table('incident_comments', fn (Blueprint $t) => $t->dropColumn('source_lang'));
        Schema::dropIfExists('content_translations');
        Schema::table('incidents', fn (Blueprint $t) => $t->dropColumn(['severity_confidence', 'severity_reasons', 'severity_source', 'recommended_responders', 'alert_radius_km', 'source_lang']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['notify_prefs', 'theme']));
        Schema::dropIfExists('auth_codes');
        Schema::dropIfExists('settings');
    }
};
