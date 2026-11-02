<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_avatar_stats_and_password(): void
    {
        $u = User::factory()->create(['password' => Hash::make('oldpass123')]);
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonStructure(['user' => ['avatar_url', 'phone'], 'stats' => ['reports', 'verified', 'confirmations']]);
        $this->patchJson('/api/v1/auth/me', ['name' => 'Rina K', 'phone' => '+880 1711 000000', 'bio' => 'Volunteer'])->assertOk()->assertJsonPath('user.phone', '+880 1711 000000');
        $this->patchJson('/api/v1/auth/me', ['phone' => 'abc'])->assertStatus(422);
        $r = $this->post('/api/v1/auth/me/avatar', ['image' => UploadedFile::fake()->image('a.jpg', 300, 300)], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull($url = $r->json('user.avatar_url'));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->postJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
        $this->postJson('/api/v1/auth/password', ['current_password' => 'oldpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertNoContent();
        $this->assertTrue(Hash::check('newpass123', $u->fresh()->password));
    }

    public function test_forgot_and_reset_password(): void
    {
        Mail::fake();
        $u = User::factory()->create(['email' => 'r@example.com']);
        $this->postJson('/api/v1/auth/forgot', ['email' => 'nobody@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/forgot', ['email' => 'r@example.com'])->assertOk();
        $this->assertSame(1, DB::table('auth_codes')->where('user_id', $u->id)->count());
        DB::table('auth_codes')->update(['code_hash' => hash('sha256', '123456')]);
        $this->postJson('/api/v1/auth/reset', ['email' => 'r@example.com', 'code' => '000000', 'password' => 'brandnew1', 'password_confirmation' => 'brandnew1'])->assertStatus(422);
        $this->postJson('/api/v1/auth/reset', ['email' => 'r@example.com', 'code' => '123456', 'password' => 'brandnew1', 'password_confirmation' => 'brandnew1'])->assertOk();
        $this->assertTrue(Hash::check('brandnew1', $u->fresh()->password));
        $this->postJson('/api/v1/auth/reset', ['email' => 'r@example.com', 'code' => '123456', 'password' => 'again1234', 'password_confirmation' => 'again1234'])->assertStatus(422);
    }
}
