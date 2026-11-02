<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// English is the default language everywhere; Bangla only when the user picks it.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('locale', 10)->default('en')->change());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('locale', 10)->default('bn')->change());
    }
};
