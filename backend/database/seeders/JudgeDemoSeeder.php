<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Additive and optional. Gives every seeded demo account (is_demo = true) the
 * documented demo only password from docs/DEMO_ACCESS.md, so judges can sign in.
 * It never creates real users and skips the system admin set in .env.
 *
 * Run after DatabaseSeeder:  php artisan db:seed --class=JudgeDemoSeeder
 */
class JudgeDemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'RescueLensDemo2026!';

    public function run(): void
    {
        $sysEmail = env('SYSTEM_ADMIN_EMAIL');

        User::query()
            ->where('is_demo', true)
            ->when($sysEmail, fn ($q) => $q->where('email', '!=', $sysEmail))
            ->each(function (User $u) {
                $u->password = self::DEMO_PASSWORD; // hashed by the model cast
                $u->save();
            });
    }
}
