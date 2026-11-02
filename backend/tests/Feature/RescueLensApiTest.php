<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** End to end API checks for the core reporting flow, roles and social login guards. */
class RescueLensApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.meta.api_key' => null, 'rescuelens.meta.api_key' => null]);
    }

    private function token(string $role = 'citizen'): array
    {
        $u = User::factory()->create(); $u->forceFill(['role' => $role])->save();
        return [$u, $u->createToken('test')->plainTextToken];
    }

    public function test_health_reports_demo_mode_without_key(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('ai.mode', 'demo');
    }

    public function test_register_login_and_role_is_not_user_writable(): void
    {
        $r = $this->postJson('/api/v1/auth/register', ['name' => 'Test', 'email' => 't@example.com', 'password' => 'secret123', 'role' => 'admin'])->assertCreated();
        $this->assertSame('citizen', $r->json('user.role'));
        $token = $r->json('token');
        $this->withToken($token)->patchJson('/api/v1/auth/me', ['role' => 'admin'])->assertOk();
        $this->assertSame('citizen', User::where('email', 't@example.com')->first()->role);
        $this->postJson('/api/v1/auth/login', ['email' => 't@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 't@example.com', 'password' => 'secret123'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_social_login_reports_not_configured(): void
    {
        config(['services.facebook.client_id' => null, 'services.google.client_id' => null]);
        $this->getJson('/api/v1/auth/facebook/redirect')->assertStatus(503)->assertJsonPath('code', 'social_not_configured');
        $this->getJson('/api/v1/auth/google/redirect')->assertStatus(503);
    }

    public function test_full_report_flow_with_demo_analysis_and_public_location_is_approximate(): void
    {
        [$u, $t] = $this->token();
        $media = $this->withToken($t)->post('/api/v1/media', ['image' => UploadedFile::fake()->image('scene.jpg', 800, 600)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $a = $this->withToken($t)->postJson('/api/v1/analyses', ['media_id' => $media, 'ai_consent' => true, 'language' => 'en', 'demo_scenario' => 'flood'])->assertSuccessful();
        $this->assertSame('demo', $a->json('analysis.mode'));
        $this->assertNotEmpty($a->json('analysis.evidence_regions'));

        // Publishing without explicit consent is refused.
        $base = ['title' => 'Waterlogging test', 'category' => 'flood', 'severity' => 'moderate', 'lat' => 23.806789, 'lng' => 90.368912, 'media_ids' => [$media]];
        $this->withToken($t)->postJson('/api/v1/incidents', $base)->assertStatus(422);
        $id = $this->withToken($t)->postJson('/api/v1/incidents', $base + ['consent_public' => true], ['Idempotency-Key' => 'rl_test_1'])->assertCreated()->json('data.id');

        $pub = $this->getJson("/api/v1/incidents/$id")->assertOk();
        $this->assertNotEquals(23.806789, $pub->json('data.location.lat'));
        $this->assertNull($pub->json('data.private'));
        $this->getJson('/api/v1/incidents')->assertOk()->assertJsonStructure(['data', 'meta' => ['next_cursor']]);
    }

    public function test_moderation_requires_moderator_role(): void
    {
        [, $citizen] = $this->token('citizen');
        [, $mod] = $this->token('moderator');
        $this->withToken($citizen)->getJson('/api/v1/mod/incidents')->assertForbidden();
        $this->app["auth"]->forgetGuards(); // the guard caches the previous user inside one test
        $this->withToken($mod)->getJson('/api/v1/mod/incidents')->assertOk();
        $this->app["auth"]->forgetGuards(); $this->flushHeaders();
        $this->getJson('/api/v1/mod/incidents')->assertUnauthorized();
    }
}
