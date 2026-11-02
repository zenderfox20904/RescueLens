<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reverse geocoded street address from the location picker. Private: visible to moderators only, never in public output.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', fn (Blueprint $t) => $t->string('address', 300)->nullable()->after('area_name'));
    }

    public function down(): void
    {
        Schema::table('incidents', fn (Blueprint $t) => $t->dropColumn('address'));
    }
};
