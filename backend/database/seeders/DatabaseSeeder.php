<?php

namespace Database\Seeders;

use App\Models\EmergencyResource;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentConfirmation;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEMO DATA ONLY. Every user and incident created here has is_demo = true and the
 * titles are prefixed with [DEMO]. None of it describes a real event.
 * Demo password for all accounts: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (\App\Models\Language::seedRows() as $l) \App\Models\Language::withTrashed()->updateOrCreate(['code' => $l['code']], $l);
        $mk = function (string $name, string $email, string $role, float $lat, float $lng) {
            $u = User::firstOrNew(['email' => $email]);
            $u->fill(['name' => $name, 'password' => 'password', 'locale' => 'en', 'alert_lat' => $lat, 'alert_lng' => $lng, 'alert_radius_km' => 10, 'ai_consent' => true]);
            $u->forceFill(['role' => $role, 'is_demo' => true, 'email_verified_at' => now()])->save();
            return $u;
        };
        // Local demo system admin. Credentials come only from .env (SYSTEM_ADMIN_EMAIL / SYSTEM_ADMIN_PASSWORD).
        $sysEmail = env('SYSTEM_ADMIN_EMAIL');
        $sysPass = env('SYSTEM_ADMIN_PASSWORD');
        if ($sysEmail && $sysPass) {
            $sys = User::firstOrNew(['email' => $sysEmail]);
            $sys->fill(['name' => 'System Admin', 'password' => $sysPass, 'locale' => 'en', 'ai_consent' => false]);
            $sys->forceFill(['role' => 'admin', 'is_demo' => true, 'email_verified_at' => now()])->save();
            $this->command?->info("System admin (local demo): {$sysEmail} / {$sysPass}");
        } else {
            $this->command?->warn('SYSTEM_ADMIN_EMAIL / SYSTEM_ADMIN_PASSWORD not set; skipping system admin.');
        }
        $admin = $mk('Demo Admin', 'admin@rescuelens.test', 'admin', 23.7806, 90.4070);
        $mod = $mk('Demo Moderator', 'moderator@rescuelens.test', 'moderator', 23.7509, 90.3935);
        $rina = $mk('রিনা (Demo)', 'rina@rescuelens.test', 'citizen', 23.8103, 90.4125);
        $karim = $mk('করিম (Demo)', 'karim@rescuelens.test', 'citizen', 23.7465, 90.3760);
        $tanvir = $mk('Tanvir (Demo)', 'tanvir@rescuelens.test', 'citizen', 23.7940, 90.4043);

        EmergencyResource::updateOrCreate(['phone' => '999', 'jurisdiction' => 'Bangladesh'], [
            'name' => 'National Emergency Service (Police, Fire, Ambulance)',
            'name_bn' => 'জাতীয় জরুরি সেবা (পুলিশ, ফায়ার সার্ভিস, অ্যাম্বুলেন্স)',
            'category' => 'national_emergency',
            'source_name' => 'Bangladesh National Portal, National Emergency Service 999',
            'source_url' => 'https://www.999.gov.bd/',
            'verified_at' => now(), 'last_reviewed_at' => now(), 'verified_by' => $admin->id, 'is_active' => true,
        ]);

        if (Incident::where('is_demo', true)->exists()) return;

        $rows = [
            [$rina, '[DEMO] ঢাকা আরিচা মহাসড়কে গাড়ি দুর্ঘটনা', 'Two cars collided near the Hemayetpur stretch. One lane appears blocked. Please drive carefully and follow police instructions.', 'road_accident', 'high', 'verified', 23.7935, 90.2570, 'Savar, Dhaka', 45, 4, $mod],
            [$karim, '[DEMO] মিরপুর ১০ এ হাঁটু পানি', 'জলাবদ্ধতা: মূল সড়কে প্রায় হাঁটু সমান পানি। রিকশা ধীরে চলছে।', 'flood', 'moderate', 'corroborated', 23.8069, 90.3687, 'Mirpur 10, Dhaka', 90, 3, null],
            [$tanvir, '[DEMO] পুরান ঢাকায় ভবনে আগুনের ধোঁয়া', 'Smoke seen from upper floor of a mixed use building. People are moving out. Call 999 if you are nearby and in danger.', 'fire', 'critical', 'reported', 23.7104, 90.4074, 'Chawkbazar, Old Dhaka', 20, 1, null],
            [$rina, '[DEMO] রাস্তায় বিদ্যুতের তার পড়ে আছে', 'A live looking electric wire is lying on a wet footpath. Keep distance. Do not touch.', 'electrical', 'high', 'reported', 23.7461, 90.3742, 'Dhanmondi 27, Dhaka', 35, 2, null],
            [$karim, '[DEMO] পুরনো ভবনের একাংশ ধসে পড়েছে', 'Part of an old wall and balcony collapsed after heavy rain. Area cordoned by locals.', 'building_collapse', 'critical', 'verified', 23.7230, 90.4160, 'Sutrapur, Dhaka', 180, 5, $mod],
        ];
        $people = [$admin, $mod, $rina, $karim, $tanvir];
        foreach ($rows as [$u, $title, $desc, $cat, $sev, $status, $lat, $lng, $area, $mins, $conf, $verifier]) {
            $i = Incident::create([
                'user_id' => $u->id, 'title' => $title, 'description' => $desc, 'category' => $cat, 'severity' => $sev,
                'status' => $status, 'lat' => $lat, 'lng' => $lng,
                'public_lat' => Incident::roundPublic($lat), 'public_lng' => Incident::roundPublic($lng),
                'area_name' => $area, 'is_demo' => true, 'ai_assisted' => true,
                'occurred_at' => now()->subMinutes($mins), 'verified_at' => $verifier ? now()->subMinutes(max(1, $mins - 10)) : null,
            ]);
            $i->forceFill(['created_at' => now()->subMinutes($mins), 'updated_at' => now()->subMinutes(max(1, $mins - 10))])->save();
            IncidentStatusHistory::create(['incident_id' => $i->id, 'actor_id' => $u->id, 'from_status' => null, 'to_status' => 'reported', 'note' => 'Reported by community member']);
            if ($verifier) IncidentStatusHistory::create(['incident_id' => $i->id, 'actor_id' => $verifier->id, 'from_status' => 'reported', 'to_status' => 'verified', 'note' => 'Checked by moderator (demo)']);
            $n = 0;
            foreach ($people as $p) {
                if ($p->id === $u->id || $n >= min($conf, 4)) continue;
                IncidentConfirmation::create(['incident_id' => $i->id, 'user_id' => $p->id, 'type' => 'confirm']);
                $n++;
            }
            IncidentComment::create(['incident_id' => $i->id, 'user_id' => $people[($i->id) % 5]->id === $u->id ? $mod->id : $people[($i->id) % 5]->id, 'body' => 'আমিও দেখেছি, সাবধানে চলুন। (demo comment)']);
            $i->forceFill(['confirmations_count' => $n, 'comments_count' => 1])->save();
        }
    }
}
