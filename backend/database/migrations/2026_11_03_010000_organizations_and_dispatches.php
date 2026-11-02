<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('account_type', 20)->default('citizen')->after('role'));
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('public_id', 26)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->index();
            $t->string('name', 160);
            $t->string('phone', 30);
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->string('address', 300)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->unsignedSmallInteger('coverage_km')->default(10);
            $t->string('service_hours', 80)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['lat', 'lng'], 'org_geo_idx');
        });
        Schema::create('dispatches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('alerted');
            $t->decimal('distance_km', 7, 2);
            $t->string('reason', 200)->nullable();
            $t->timestamps();
            $t->unique(['incident_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
        Schema::dropIfExists('organizations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('account_type'));
    }
};
