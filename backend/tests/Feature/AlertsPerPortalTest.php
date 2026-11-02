<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A new report reaches every portal: nearby users, NGOs, help centers, category matched responders and admins. */
class AlertsPerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $type): User
    {
        $u = User::factory()->create();
        $u->forceFill(['account_type' => $type])->save();
        Organization::query()->forceCreate(['type' => $type, 'name' => "Org $type", 'phone' => '+1 555 0100', 'lat' => 23.771, 'lng' => 90.381,
            'coverage_km' => 20, 'verified_at' => now(), 'user_id' => $u->id, 'public_id' => strtolower((string) \Illuminate\Support\Str::ulid())]);
        return $u;
    }

    public function test_flood_report_alerts_every_portal_and_each_sees_it_in_their_inbox(): void
    {
        $citizen = User::factory()->create();
        $citizen->forceFill(['alert_lat' => 23.772, 'alert_lng' => 90.382, 'alert_radius_km' => 10])->save();
        $ngo = $this->owner('ngo');
        $help = $this->owner('help_center');
        $fire = $this->owner('fire_service');
        $police = $this->owner('police'); // not mapped to flood
        $admin = User::factory()->create(); $admin->forceFill(['role' => 'admin'])->save();
        $mod = User::factory()->create(); $mod->forceFill(['role' => 'moderator'])->save();

        $reporter = User::factory()->create();
        $this->actingAs($reporter)->postJson('/api/v1/incidents', ['title' => 'Flooded street', 'category' => 'flood', 'severity' => 'high',
            'lat' => 23.7705, 'lng' => 90.3805, 'consent_public' => true])->assertCreated();

        foreach (['nearby' => [$citizen], 'dispatch' => [$ngo, $help, $fire], 'review' => [$admin, $mod]] as $type => $users) {
            foreach ($users as $u) {
                $types = collect($this->actingAs($u)->getJson('/api/v1/notifications')->assertOk()->json('data'))->pluck('type');
                $this->assertContains($type, $types->all(), "user {$u->id} missing $type alert");
            }
        }
        $this->assertSame([], $this->actingAs($police)->getJson('/api/v1/notifications')->json('data'));
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $reporter->id]);
    }
}
