<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->ulid('public_id')->nullable()->unique()->after('id');
            $t->string('role', 20)->default('citizen')->index()->after('password');
            $t->string('locale', 5)->default('bn')->after('role');
            $t->decimal('alert_lat', 9, 6)->nullable();
            $t->decimal('alert_lng', 9, 6)->nullable();
            $t->unsignedSmallInteger('alert_radius_km')->default(5);
            $t->boolean('ai_consent')->default(false);
            $t->boolean('is_demo')->default(false);
        });

        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160);
            $t->text('description')->nullable();
            $t->string('category', 30)->index();
            $t->string('severity', 12)->index();
            $t->string('status', 20)->default('reported')->index();
            $t->decimal('lat', 9, 6);
            $t->decimal('lng', 9, 6);
            $t->decimal('public_lat', 9, 6);
            $t->decimal('public_lng', 9, 6);
            $t->string('area_name', 120)->nullable();
            $t->boolean('is_anonymous')->default(false);
            $t->boolean('is_demo')->default(false)->index();
            $t->boolean('ai_assisted')->default(false);
            $t->foreignId('merged_into_id')->nullable()->constrained('incidents')->nullOnDelete();
            $t->unsignedInteger('confirmations_count')->default(0);
            $t->unsignedInteger('disputes_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            $t->timestamp('occurred_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['public_lat', 'public_lng']);
            $t->index('updated_at');
        });

        Schema::create('incident_media', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $t->string('disk', 20)->default('local');
            $t->string('path');
            $t->string('mime', 40);
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->unsignedInteger('size_bytes');
            $t->char('sha256', 64)->index();
            $t->boolean('metadata_stripped')->default(true);
            $t->timestamps();
        });

        Schema::create('ai_analyses', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_id')->constrained('incident_media')->cascadeOnDelete();
            $t->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mode', 10); // live | demo
            $t->string('provider', 40);
            $t->string('vision_model', 60)->nullable();
            $t->string('segmentation_model', 60)->nullable();
            $t->string('status', 20)->default('pending');
            $t->string('language', 5)->default('bn');
            $t->string('primary_hazard', 30)->nullable();
            $t->string('severity', 12)->nullable();
            $t->boolean('requires_human_review')->default(true);
            $t->json('result')->nullable();
            $t->string('error_code', 40)->nullable();
            $t->unsignedInteger('latency_ms')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_evidence_regions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('analysis_id')->constrained('ai_analyses')->cascadeOnDelete();
            $t->string('label', 80);
            $t->string('source', 20); // sam-3.1 | demo
            $t->unsignedInteger('x1');
            $t->unsignedInteger('y1');
            $t->unsignedInteger('x2');
            $t->unsignedInteger('y2');
            $t->unsignedInteger('image_width');
            $t->unsignedInteger('image_height');
            $t->decimal('confidence', 5, 4)->nullable();
            $t->timestamps();
        });

        Schema::create('incident_confirmations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10); // confirm | dispute
            $t->timestamps();
            $t->unique(['incident_id', 'user_id']);
        });

        Schema::create('incident_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('incident_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('note', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('emergency_resources', function (Blueprint $t) {
            $t->id();
            $t->string('name', 160);
            $t->string('name_bn', 160)->nullable();
            $t->string('category', 30);
            $t->string('phone', 30)->nullable();
            $t->string('jurisdiction', 120);
            $t->string('source_name', 160);
            $t->string('source_url', 500);
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('last_reviewed_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('incident_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('type', 30);
            $t->string('title', 200);
            $t->string('body', 500)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('key', 80);
            $t->foreignId('incident_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'key']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 60)->index();
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->char('ip_hash', 64)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'idempotency_keys', 'user_notifications', 'emergency_resources', 'incident_status_history',
            'incident_comments', 'incident_confirmations', 'ai_evidence_regions', 'ai_analyses', 'incident_media', 'incidents'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['public_id', 'role', 'locale', 'alert_lat', 'alert_lng', 'alert_radius_km', 'ai_consent', 'is_demo']));
    }
};
