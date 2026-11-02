<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->char('country_code', 2)->default('BD');
            $t->string('distance_unit', 2)->default('km');
            $t->string('timezone', 64)->nullable();
        });
        Schema::table('emergency_resources', function (Blueprint $t) {
            $t->char('country_code', 2)->default('BD')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['country_code', 'distance_unit', 'timezone']));
        Schema::table('emergency_resources', fn (Blueprint $t) => $t->dropColumn('country_code'));
    }
};
