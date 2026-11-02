<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $type, float $lat, float $lng, bool $verified = true, ?int $userId = null): Organization
    {
        return Organization::query()->forceCreate(['type' => $type, 'name' => ucfirst($type).' '.$lat, 'phone' => '+1 555 0100', 'lat' => $lat, 'lng' => $lng,
            'coverage_km' => 10, 'verified_at' => $verified ? now() : null, 'user_id' => $userId, 'public_id' => strtolower((string) \Illuminate\Support\Str::ulid())]);
    }

    public function test_org_registration_starts_unverified_and_role_is_not_writable(): void
    {
        $r = $this->postJson('/api/v1/auth/register', ['name' => 'City Hospital', 'email' => 'h@example.com', 'password' => 'secret123', 'account_type' => 'hospital', 'role' => 'admin',
            'organization' => ['name' => 'City Hospital', 'phone' => '+1 555 0101', 'lat' => 40.7, 'lng' => -74.0, 'coverage_km' => 15, 'service_hours' => '24/7']])->assertCreated();
        $this->assertSame('citizen', $r->json('user.role'));
        $this->assertSame('hospital', $r->json('user.account_type'));
        $this->assertFalse($r->json('user.organization.verified'));
        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'account_type' => 'police'])->assertStatus(422);
    }

    public function test_nearby_help_returns_only_verified_within_radius_sorted(): void
    {
        $this->org('hospital', 23.7500, 90.3900);
        $this->org('police', 23.7600, 90.3900);
        $this->org('hospital', 23.7510, 90.3900, false); // unverified, hidden
        $this->org('hospital', 24.5, 90.39); // far away
        $d = $this->getJson('/api/v1/nearby-help?lat=23.75&lng=90.39&radius_km=5')->assertOk()->json('data');
        $this->assertCount(2, $d);
        $this->assertSame('hospital', $d[0]['type']);
        $this->assertTrue($d[0]['distance_km'] <= $d[1]['distance_km']);
        $this->assertCount(1, $this->getJson('/api/v1/nearby-help?lat=23.75&lng=90.39&radius_km=5&types=police')->json('data'));
    }

    public function test_fire_incident_dispatches_fire_service_and_responder_updates_status(): void
    {
        $responder = User::factory()->create();
        $fire = $this->org('fire_service', 23.7110, 90.4080, true, $responder->id);
        $this->org('police', 23.7110, 90.4080); // police not mapped to fire
        $reporter = User::factory()->create();
        $id = $this->actingAs($reporter)->postJson('/api/v1/incidents', ['title' => 'Smoke from building', 'category' => 'fire', 'severity' => 'critical', 'lat' => 23.7104, 'lng' => 90.4074, 'consent_public' => true])
            ->assertCreated()->json('data.id');
        $list = $this->getJson("/api/v1/incidents/$id/dispatches")->assertOk();
        $this->assertSame(['fire_service'], collect($list->json('data'))->pluck('organization.type')->unique()->values()->all());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $responder->id, 'type' => 'dispatch']);
        $dispatch = Dispatch::first();
        $this->actingAs($reporter)->postJson("/api/v1/org/dispatches/{$dispatch->id}/status", ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($responder)->postJson("/api/v1/org/dispatches/{$dispatch->id}/status", ['status' => 'en_route'])->assertOk()->assertJsonPath('data.status', 'en_route');
        $this->assertSame($fire->id, $dispatch->organization_id);
    }

    public function test_responder_mapping(): void
    {
        $this->assertEqualsCanonicalizing(['ambulance', 'hospital', 'police'], \App\Services\DispatchService::responderTypes('road_accident', 'high'));
    }
}
