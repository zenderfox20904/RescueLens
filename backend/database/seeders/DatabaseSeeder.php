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
 * posts stay labeled demo until `php artisan rescuelens:analyze-seeded` attaches a photo and a live AI analysis. None of it describes a real event.
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
        $rina = $mk('Rina (Demo)', 'rina@rescuelens.test', 'citizen', 23.8103, 90.4125);
        $karim = $mk('Karim (Demo)', 'karim@rescuelens.test', 'citizen', 23.7465, 90.3760);
        $tanvir = $mk('Tanvir (Demo)', 'tanvir@rescuelens.test', 'citizen', 23.7940, 90.4043);

        EmergencyResource::updateOrCreate(['phone' => '999', 'jurisdiction' => 'Bangladesh'], [
            'name' => 'National Emergency Service (Police, Fire, Ambulance)',
            'name_bn' => 'জাতীয় জরুরি সেবা (পুলিশ, ফায়ার সার্ভিস, অ্যাম্বুলেন্স)',
            'category' => 'national_emergency',
            'source_name' => 'Bangladesh National Portal, National Emergency Service 999',
            'source_url' => 'https://www.999.gov.bd/',
            'verified_at' => now(), 'last_reviewed_at' => now(), 'verified_by' => $admin->id, 'is_active' => true,
        ]);

        // Official numbers only from official sources; anything not checked stays unverified and is hidden from users.
        foreach ([
            ['IN', '112', 'Emergency Response Support System (Police, Fire, Ambulance)', 'Government of India, ERSS 112', 'https://112.gov.in/', true],
            ['US', '911', 'Emergency services (Police, Fire, EMS)', '911.gov, National 911 Program', 'https://www.911.gov/', true],
            ['GB', '999', 'Emergency services (Police, Fire, Ambulance, Coastguard)', 'Pending official source review', 'https://www.gov.uk/', false],
            ['NG', '112', 'National emergency number', 'Pending official source review', 'https://www.ncc.gov.ng/', false],
            ['ID', '112', 'National emergency number', 'Pending official source review', 'https://www.kominfo.go.id/', false],
        ] as [$cc, $phone, $name, $src, $url, $ok]) {
            EmergencyResource::updateOrCreate(['phone' => $phone, 'country_code' => $cc], ['name' => $name, 'category' => 'national_emergency', 'jurisdiction' => $cc,
                'source_name' => $src, 'source_url' => $url, 'verified_at' => $ok ? now() : null, 'last_reviewed_at' => $ok ? now() : null, 'verified_by' => $ok ? $admin->id : null, 'is_active' => true]);
        }

        // Demo responder organizations (verified, fictional names, demo phone numbers) in several cities.
        $orgs = [
            // city, type, name, lat, lng, coverage, hours
            ['BD', 'hospital', 'Demo City Hospital Dhanmondi', 23.7465, 90.3730, 15, '24/7'],
            ['BD', 'fire_service', 'Demo Fire Station Lalbagh', 23.7190, 90.3880, 12, '24/7'],
            ['BD', 'police', 'Demo Police Station Tejgaon', 23.7640, 90.3990, 10, '24/7'],
            ['BD', 'ambulance', 'Demo Ambulance Service Mirpur', 23.8040, 90.3650, 20, '24/7'],
            ['BD', 'help_center', 'Demo Flood Relief Center Mohammadpur', 23.7590, 90.3590, 15, '08:00 to 22:00'],
            ['BD', 'ngo', 'Demo Relief NGO Dhaka', 23.7700, 90.3800, 20, '08:00 to 20:00'],
            ['BD', 'volunteer', 'Demo Volunteer Rescue Team Savar', 23.8450, 90.2600, 25, 'On call'],
            ['IN', 'hospital', 'Demo General Hospital Connaught Place', 28.6315, 77.2167, 15, '24/7'],
            ['IN', 'fire_service', 'Demo Fire Station Karol Bagh', 28.6519, 77.1909, 12, '24/7'],
            ['IN', 'police', 'Demo Police Station Saket', 28.5245, 77.2066, 10, '24/7'],
            ['NG', 'hospital', 'Demo Teaching Hospital Ikeja', 6.6018, 3.3515, 15, '24/7'],
            ['NG', 'fire_service', 'Demo Fire Service Lagos Island', 6.4541, 3.3947, 12, '24/7'],
            ['NG', 'ambulance', 'Demo Ambulance Lekki', 6.4474, 3.4720, 20, '24/7'],
            ['GB', 'hospital', 'Demo Royal Hospital Whitechapel', 51.5194, -0.0590, 10, '24/7'],
            ['GB', 'fire_service', 'Demo Fire Station Soho', 51.5136, -0.1340, 8, '24/7'],
            ['GB', 'police', 'Demo Police Station Westminster', 51.4975, -0.1357, 8, '24/7'],
            ['US', 'hospital', 'Demo Medical Center Manhattan', 40.7644, -73.9550, 10, '24/7'],
            ['US', 'fire_service', 'Demo Engine Company Midtown', 40.7549, -73.9840, 6, '24/7'],
            ['US', 'police', 'Demo Precinct Lower Manhattan', 40.7128, -74.0060, 6, '24/7'],
            ['ID', 'hospital', 'Demo Central Hospital Menteng', -6.1960, 106.8330, 15, '24/7'],
            ['ID', 'help_center', 'Demo Flood Response Post Kampung Melayu', -6.2250, 106.8670, 15, '24/7'],
            ['ID', 'fire_service', 'Demo Fire Station Jakarta Pusat', -6.1750, 106.8270, 12, '24/7'],
        ];
        foreach ($orgs as $k => [$cc, $type, $name, $lat, $lng, $cov, $hours]) {
            \App\Models\Organization::withTrashed()->where('name', $name)->exists() || \App\Models\Organization::query()->forceCreate([
                'type' => $type, 'name' => $name, 'phone' => '+000 555 '.str_pad((string) ($k + 100), 4, '0', STR_PAD_LEFT), 'lat' => $lat, 'lng' => $lng,
                'country_code' => $cc, 'coverage_km' => $cov, 'service_hours' => $hours, 'verified_at' => now(), 'is_demo' => true,
                'public_id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'address' => $name.' (demo address)']);
        }
        // A responder login for the demo fire station, so the dispatch inbox can be tried.
        $fire = $mk('Demo Fire Station Lalbagh', 'fire@rescuelens.test', 'citizen', 23.7190, 90.3880);
        $fire->forceFill(['account_type' => 'fire_service'])->save();
        \App\Models\Organization::where('name', 'Demo Fire Station Lalbagh')->update(['user_id' => $fire->id]);
        // One login per portal so every account type can be demoed (same demo password).
        foreach ([['ngo@rescuelens.test', 'ngo', 'Demo Relief NGO Dhaka'], ['helpcenter@rescuelens.test', 'help_center', 'Demo Flood Relief Center Mohammadpur'],
            ['ambulance@rescuelens.test', 'ambulance', 'Demo Ambulance Service Mirpur'], ['hospital@rescuelens.test', 'hospital', 'Demo City Hospital Dhanmondi']] as [$em, $type, $name]) {
            $o = \App\Models\Organization::where('name', $name)->first();
            $acct = $mk($name, $em, 'citizen', (float) ($o?->lat ?? 23.77), (float) ($o?->lng ?? 90.38));
            $acct->forceFill(['account_type' => $type])->save();
            $o?->update(['user_id' => $acct->id]);
        }

        $rows = [
            [$rina, 'Two car collision on Dhaka Aricha Highway', 'Two cars collided near the Hemayetpur stretch. One lane appears blocked. Please drive carefully and follow police instructions.', 'road_accident', 'high', 'verified', 23.7935, 90.2570, 'Savar, Dhaka', 45, 4, $mod],
            [$karim, 'Knee deep water at Mirpur 10', 'Waterlogging on the main road, about knee deep. Rickshaws are moving slowly.', 'flood', 'moderate', 'corroborated', 23.8069, 90.3687, 'Mirpur 10, Dhaka', 90, 3, null],
            [$tanvir, 'Smoke from a building in Old Dhaka', 'Smoke seen from upper floor of a mixed use building. People are moving out. Call 999 if you are nearby and in danger.', 'fire', 'critical', 'reported', 23.7104, 90.4074, 'Chawkbazar, Old Dhaka', 20, 1, null],
            [$rina, 'Fallen electric wire on footpath', 'A live looking electric wire is lying on a wet footpath. Keep distance. Do not touch.', 'electrical', 'high', 'reported', 23.7461, 90.3742, 'Dhanmondi 27, Dhaka', 35, 2, null],
            [$karim, 'Part of an old building collapsed', 'Part of an old wall and balcony collapsed after heavy rain. Area cordoned by locals.', 'building_collapse', 'critical', 'verified', 23.7230, 90.4160, 'Sutrapur, Dhaka', 180, 5, $mod],
            [$tanvir, 'Waterlogging under Minto Bridge', 'Heavy rain has flooded the underpass. Cars are stalling. Use an alternate route.', 'flood', 'high', 'corroborated', 28.6328, 77.2280, 'Minto Road, New Delhi', 60, 3, null],
            [$rina, 'Market fire near Balogun', 'Thick smoke from a row of shops. Traders are moving goods out. Keep the road clear for fire trucks.', 'fire', 'critical', 'reported', 6.4560, 3.3880, 'Lagos Island, Lagos', 25, 2, null],
            [$karim, 'Cyclist injured at junction', 'A cyclist was hit at a busy junction and is being helped by passers by. Ambulance requested.', 'road_accident', 'high', 'verified', 51.5155, -0.0720, 'Aldgate, London', 40, 3, $mod],
            [$tanvir, 'Ice on sidewalk causing falls', 'Several people have slipped on an icy stretch of sidewalk. Walk carefully.', 'snow_ice', 'moderate', 'reported', 40.7580, -73.9855, 'Times Square, New York', 70, 2, null],
            [$rina, 'River overflow in Kampung Melayu', 'The Ciliwung river has overflowed into nearby lanes. Water is rising slowly. Move valuables up.', 'flood', 'high', 'corroborated', -6.2240, 106.8660, 'Kampung Melayu, Jakarta', 110, 4, null],
        ];
        $rows = array_values(array_filter($rows, fn ($r) => ! Incident::withTrashed()->whereIn('title', [$r[1], '[DEMO] '.$r[1]])->exists()));
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
            IncidentComment::create(['incident_id' => $i->id, 'user_id' => $people[($i->id) % 5]->id === $u->id ? $mod->id : $people[($i->id) % 5]->id, 'body' => 'I saw this too, please be careful.']);
            $i->forceFill(['confirmations_count' => $n, 'comments_count' => 1])->save();
        }
    }
}
