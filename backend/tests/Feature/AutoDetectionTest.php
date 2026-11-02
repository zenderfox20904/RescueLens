<?php

namespace Tests\Feature;

use App\Models\CameraFeed;
use App\Models\Incident;
use App\Models\User;
use App\Services\Camera\CameraIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 3: one tap auto post with consent and undo, CCTV feeds with allowlist, moderation and feed thresholds, and the dev only Ollama provider. */
class AutoDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['rescuelens.meta.api_key' => null, 'rescuelens.ai_provider' => 'meta']);
    }

    private function user(string $role = 'citizen'): array
    {
        $u = User::factory()->create(); $u->forceFill(['role' => $role])->save();
        return [$u, $u->createToken('t')->plainTextToken];
    }

    private function analyzed(string $t): array
    {
        $m = $this->withToken($t)->post('/api/v1/media', ['image' => UploadedFile::fake()->image('a.jpg', 640, 480)], ['Accept' => 'application/json'])->json('id');
        $a = $this->withToken($t)->postJson('/api/v1/analyses', ['media_id' => $m, 'ai_consent' => true, 'language' => 'en', 'demo_scenario' => 'fire'])->json('analysis');
        return [$m, $a];
    }

    public function test_auto_post_requires_opt_in_with_explicit_consent_and_can_be_undone(): void
    {
        [$u, $t] = $this->user();
        [$m, $a] = $this->analyzed($t);
        $body = ['title' => 'Fire', 'category' => 'fire', 'severity' => 'high', 'lat' => 23.7, 'lng' => 90.4, 'media_ids' => [$m], 'analysis_id' => $a['id'], 'consent_public' => true, 'auto_post' => true];
        $this->withToken($t)->postJson('/api/v1/incidents', $body)->assertForbidden();

        $this->withToken($t)->patchJson('/api/v1/auth/me', ['auto_post_enabled' => true])->assertStatus(422); // consent missing
        $this->withToken($t)->patchJson('/api/v1/auth/me', ['auto_post_enabled' => true, 'auto_post_consent' => true])->assertOk()->assertJsonPath('user.auto_post_enabled', true);
        $this->assertNotNull($u->fresh()->auto_post_consent_at);

        $r = $this->withToken($t)->postJson('/api/v1/incidents', $body)->assertCreated()
            ->assertJsonPath('data.source', 'auto_post')->assertJsonPath('data.ai_unverified', true)->assertJsonPath('undo_seconds', 10);
        $this->withToken($t)->postJson('/api/v1/incidents/'.$r->json('data.id').'/withdraw')->assertNoContent();
        $this->assertSame('withdrawn', Incident::first()->status);
    }

    public function test_camera_feed_allowlist_and_admin_only(): void
    {
        [, $cit] = $this->user();
        [, $adm] = $this->user('admin');
        $feed = ['name' => 'Cam 1', 'kind' => 'snapshot_url', 'snapshot_url' => 'https://evil.example/snap.jpg', 'lat' => 23.75, 'lng' => 90.39];
        $this->withToken($cit)->postJson('/api/v1/mod/admin/camera-feeds', $feed)->assertForbidden();
        $this->app['auth']->forgetGuards();
        config(['rescuelens.camera.allowed_hosts' => ['cams.example.org']]);
        $this->withToken($adm)->postJson('/api/v1/mod/admin/camera-feeds', $feed)->assertStatus(422);
        $this->withToken($adm)->postJson('/api/v1/mod/admin/camera-feeds', ['snapshot_url' => 'http://cams.example.org/a.jpg'] + $feed)->assertStatus(422);
        $this->withToken($adm)->postJson('/api/v1/mod/admin/camera-feeds', ['snapshot_url' => 'https://cams.example.org/a.jpg'] + $feed)->assertCreated()
            ->assertJsonPath('data.snapshot_host', 'cams.example.org');
    }

    public function test_camera_frame_goes_to_moderation_hidden_unless_thresholds_pass_and_dedupes(): void
    {
        [, $adm] = $this->user('admin');
        $id = $this->withToken($adm)->postJson('/api/v1/mod/admin/camera-feeds', ['name' => 'Upload cam', 'kind' => 'upload', 'lat' => 23.75, 'lng' => 90.39])->json('data.id');

        // Threshold above any demo confidence: incident is created for moderation but hidden from the public feed.
        config(['rescuelens.camera.min_confidence' => 1.01]);
        $this->withToken($adm)->post("/api/v1/mod/admin/camera-feeds/$id/frames", ['image' => UploadedFile::fake()->image('f.jpg', 640, 480)], ['Accept' => 'application/json'])->assertStatus(202);
        $i = Incident::first();
        $this->assertSame('camera', $i->source);
        $this->assertTrue($i->ai_unverified);
        $this->assertSame('reported', $i->status); // never auto verified
        $this->assertFalse($i->feed_eligible);
        $this->app['auth']->forgetGuards(); $this->flushHeaders();
        $this->getJson('/api/v1/incidents/'.$i->public_id)->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/v1/incidents')->json('data'));

        // A second frame of the same scene is deduplicated into the existing incident.
        $this->withToken($adm)->post("/api/v1/mod/admin/camera-feeds/$id/frames", ['image' => UploadedFile::fake()->image('f.jpg', 640, 480)], ['Accept' => 'application/json'])->assertStatus(202);
        $this->assertSame(1, Incident::count());
        $this->withToken($adm)->getJson('/api/v1/mod/incidents')->assertOk()->assertJsonCount(1, 'data');

        // Moderator verification is the only way to verified, and it makes the incident visible.
        $this->withToken($adm)->postJson('/api/v1/mod/incidents/'.$i->public_id.'/status', ['status' => 'verified'])->assertOk();
        $this->assertTrue($i->fresh()->feed_eligible);
        $this->assertFalse($i->fresh()->ai_unverified);
    }

    public function test_feed_eligibility_rule(): void
    {
        $this->assertTrue(CameraIngestService::feedEligible('critical', 0.9, true));
        $this->assertFalse(CameraIngestService::feedEligible('moderate', 0.99, true));
        $this->assertFalse(CameraIngestService::feedEligible('high', 0.5, true));
        $this->assertFalse(CameraIngestService::feedEligible('high', null, true));
        $this->assertFalse(CameraIngestService::feedEligible('high', 0.9, false));
    }

    public function test_dev_ollama_provider_is_selected_by_env_and_labeled_dev(): void
    {
        config(['rescuelens.ai_provider' => 'ollama']);
        $this->refreshApplication(); config(['rescuelens.ai_provider' => 'ollama']);
        (new \App\Providers\AppServiceProvider($this->app))->register();
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => "```json\n".json_encode(\App\Services\AI\DemoFixtures::analysis('fire', 'en') + ['is_incident_scene' => true, 'summary_en' => 'x', 'suggested_title_en' => 'x', 'vehicles_involved' => [], 'people_at_risk' => 'possible', 'people_at_risk_reason' => 'x', 'severity_reason' => 'x'])."\n```"]])]);
        $p = app(\App\Services\AI\VisionAnalysisProvider::class);
        $this->assertSame('ollama-dev', $p->name());
        $this->assertSame('dev', $p->mode());
    }
}
