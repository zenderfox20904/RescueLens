<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $t) {
            $t->timestamp('edited_at')->nullable();
            $t->foreignId('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', fn (Blueprint $t) => $t->dropColumn(['edited_at', 'deleted_by']));
    }
};
