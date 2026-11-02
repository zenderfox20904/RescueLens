<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTrashTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $u): string
    {
        Sanctum::actingAs($u);
        return $this->postJson('/api/v1/incidents', ['title' => 'Tree on road', 'category' => 'road_accident', 'severity' => 'moderate', 'lat' => 23.78, 'lng' => 90.39, 'consent_public' => true])
            ->assertCreated()->json('data.id');
    }

    public function test_owner_edits_trashes_and_restores(): void
    {
        $owner = User::factory()->create();
        $id = $this->report($owner);
        $this->patchJson("/api/v1/incidents/$id", ['title' => 'Fallen tree blocking lane', 'category' => 'other'])
            ->assertOk()->assertJsonPath('data.title', 'Fallen tree blocking lane')->assertJsonPath('data.can_manage', true);
        $this->assertNotNull($this->getJson("/api/v1/incidents/$id")->json('data.edited_at'));

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/incidents/$id", ['title' => 'Hijack'])->assertForbidden();
        $this->deleteJson("/api/v1/incidents/$id")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/incidents/$id")->assertOk();
        $this->getJson("/api/v1/incidents/$id")->assertNotFound();
        $this->getJson('/api/v1/me/trash')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/incidents/$id/restore")->assertOk();
        $this->getJson('/api/v1/me/trash')->assertJsonCount(0, 'data');
    }

    public function test_moderator_manages_any_post(): void
    {
        $id = $this->report(User::factory()->create());
        $mod = User::factory()->create();
        $mod->forceFill(['role' => 'moderator'])->save();
        Sanctum::actingAs($mod);
        $this->patchJson("/api/v1/incidents/$id", ['severity' => 'high'])->assertOk();
        $this->deleteJson("/api/v1/incidents/$id")->assertOk();
        $this->getJson('/api/v1/me/trash?all=1')->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/incidents/$id/restore")->assertOk();
    }
}
