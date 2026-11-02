<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'avatar_path')) $t->string('avatar_path')->nullable();
            if (! Schema::hasColumn('users', 'phone')) $t->string('phone', 32)->nullable();
            if (! Schema::hasColumn('users', 'bio')) $t->string('bio', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_path', 'phone', 'bio']));
    }
};
