<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPrefsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_notifications(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        $a = UserNotification::create(['user_id' => $u->id, 'type' => 'nearby', 'title' => 'A']);
        UserNotification::create(['user_id' => $u->id, 'type' => 'nearby', 'title' => 'B']);
        $theirs = UserNotification::create(['user_id' => $other->id, 'type' => 'nearby', 'title' => 'C']);
        Sanctum::actingAs($u);

        $this->patchJson("/api/v1/notifications/{$a->id}", ['read' => true])->assertOk()->assertJson(['unread' => 1]);
        $this->patchJson("/api/v1/notifications/{$a->id}", ['read' => false])->assertJson(['unread' => 2]);
        $this->deleteJson("/api/v1/notifications/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$a->id}")->assertNoContent();
        $this->deleteJson('/api/v1/notifications')->assertNoContent();
        $this->assertSame(0, UserNotification::where('user_id', $u->id)->count());
        $this->assertSame(1, UserNotification::where('user_id', $other->id)->count());
    }

    public function test_preferences_filter_alerts_and_quiet_hours(): void
    {
        $u = User::factory()->create(['timezone' => 'UTC']);
        Sanctum::actingAs($u);
        $this->patchJson('/api/v1/auth/me', ['theme' => 'dark', 'notify_prefs' => ['categories' => ['fire'], 'quiet_start' => '22:00', 'quiet_end' => '06:00']])
            ->assertOk()->assertJsonPath('user.theme', 'dark')->assertJsonPath('user.notify_prefs.categories', ['fire']);
        $this->patchJson('/api/v1/auth/me', ['role' => 'admin', 'notify_prefs' => ['categories' => ['nope']]])->assertStatus(422);
        $u->refresh();
        $this->assertSame('citizen', $u->role);
        $night = new \DateTime('2026-11-02 23:00:00', new \DateTimeZone('UTC'));
        $day = new \DateTime('2026-11-02 12:00:00', new \DateTimeZone('UTC'));
        $this->assertFalse($u->wantsAlert('flood', 'high', $day));
        $this->assertTrue($u->wantsAlert('fire', 'high', $day));
        $this->assertFalse($u->wantsAlert('fire', 'high', $night));
        $this->assertTrue($u->wantsAlert('fire', 'critical', $night));
    }
}
