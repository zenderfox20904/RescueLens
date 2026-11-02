<?php

namespace Tests\Feature;

use App\Models\EmergencyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternationalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_stores_locale_country_units_and_timezone(): void
    {
        $u = User::factory()->create(); $t = $u->createToken('t')->plainTextToken;
        $this->withToken($t)->patchJson('/api/v1/auth/me', ['locale' => 'en', 'country_code' => 'us', 'distance_unit' => 'mi', 'timezone' => 'America/New_York'])
            ->assertOk()->assertJsonPath('user.country_code', 'US')->assertJsonPath('user.distance_unit', 'mi')->assertJsonPath('user.locale', 'en');
        $this->withToken($t)->patchJson('/api/v1/auth/me', ['timezone' => 'Mars/Base'])->assertStatus(422);
    }

    public function test_emergency_directory_returns_only_verified_entries_for_the_country(): void
    {
        $base = ['category' => 'national_emergency', 'jurisdiction' => 'X', 'source_name' => 'S', 'source_url' => 'https://example.org', 'is_active' => true];
        EmergencyResource::create($base + ['name' => 'BD verified', 'phone' => '999', 'country_code' => 'BD', 'verified_at' => now(), 'last_reviewed_at' => now()]);
        EmergencyResource::create($base + ['name' => 'BD unverified', 'phone' => '111', 'country_code' => 'BD']);
        $this->getJson('/api/v1/emergency-resources?country=BD')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.phone', '999');
        $this->getJson('/api/v1/emergency-resources?country=FR')->assertOk()->assertJsonCount(0, 'data');
    }
}
