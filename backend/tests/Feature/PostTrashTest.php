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

    public function test_threaded_comments_edit_delete_restore_like(): void
    {
        $owner = User::factory()->create();
        $id = $this->report($owner);
        \App\Models\Incident::where('public_id', $id)->update(['status' => 'verified', 'feed_eligible' => true]);
        $a = User::factory()->create();
        Sanctum::actingAs($a);
        $c = $this->postJson("/api/v1/incidents/$id/comments", ['body' => 'Road blocked here'])->assertCreated()->json('data.id');
        $b = User::factory()->create();
        Sanctum::actingAs($b);
        $reply = $this->postJson("/api/v1/incidents/$id/comments", ['body' => 'Confirmed', 'parent_id' => $c])->assertCreated()->assertJsonPath('data.parent_id', $c)->json('data.id');
        $this->postJson("/api/v1/comments/$c/like")->assertOk()->assertJsonPath('data.liked', true);
        $this->patchJson("/api/v1/comments/$c", ['body' => 'hijack'])->assertForbidden();
        $this->deleteJson("/api/v1/comments/$c")->assertForbidden();
        $this->patchJson("/api/v1/comments/$reply", ['body' => 'Confirmed, still blocked'])->assertOk()->assertJsonPath('data.body', 'Confirmed, still blocked');
        $this->getJson("/api/v1/incidents/$id/comments")->assertJsonCount(2, 'data')->assertJsonPath('data.0.liked', true);
        Sanctum::actingAs($a);
        $this->deleteJson("/api/v1/comments/$c")->assertOk();
        $this->getJson("/api/v1/incidents/$id/comments")->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/comments/$c/restore")->assertOk();
        $this->getJson("/api/v1/incidents/$id/comments")->assertJsonCount(2, 'data');
    }

    public function test_profile_timeline_hides_anonymous_from_others(): void
    {
        $a = \App\Models\User::factory()->create(['role' => 'citizen']);
        $b = \App\Models\User::factory()->create(['role' => 'citizen']);
        $base = ['category' => 'flood', 'severity' => 'low', 'lat' => 23.78, 'lng' => 90.41, 'consent_public' => true];
        $this->actingAs($a, 'sanctum')->postJson('/api/v1/incidents', $base + ['title' => 'Named post'])->assertCreated();
        $this->actingAs($a, 'sanctum')->postJson('/api/v1/incidents', $base + ['title' => 'Secret post', 'is_anonymous' => true])->assertCreated();
        $own = $this->actingAs($a, 'sanctum')->getJson('/api/v1/incidents?user='.$a->public_id)->assertOk()->json('data');
        $this->assertCount(2, $own);
        $this->assertTrue($own[0]['can_manage']);
        $other = $this->actingAs($b, 'sanctum')->getJson('/api/v1/incidents?user='.$a->public_id)->assertOk()->json('data');
        $titles = collect($other)->pluck('title')->implode('|');
        $this->assertStringNotContainsString('Secret', $titles);
        $this->assertFalse($other[0]['can_manage'] ?? true);
        $p = $this->getJson('/api/v1/users/'.$a->public_id.'/profile')->assertOk()->json('data');
        $this->assertArrayNotHasKey('email', $p);
    }

    public function test_force_delete_and_empty_trash_owner_only(): void
    {
        $owner = User::factory()->create();
        $a = $this->report($owner); $b = $this->report($owner);
        $this->deleteJson("/api/v1/incidents/$a")->assertOk();
        $this->deleteJson("/api/v1/incidents/$b")->assertOk();
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/incidents/$a/force")->assertForbidden();
        $this->deleteJson('/api/v1/me/trash')->assertOk()->assertJsonPath('data.deleted', 0);
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/incidents/$a/force")->assertOk();
        $this->assertDatabaseMissing('incidents', ['public_id' => $a]);
        $this->postJson("/api/v1/incidents/$a/restore")->assertNotFound();
        $this->deleteJson('/api/v1/me/trash')->assertOk()->assertJsonPath('data.deleted', 1);
        $this->getJson('/api/v1/me/trash')->assertJsonCount(0, 'data');
    }
}
