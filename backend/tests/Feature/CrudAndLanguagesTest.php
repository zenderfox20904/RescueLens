<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Language;
use App\Models\User;
use App\Services\I18n\TextTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudAndLanguagesTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): string
    {
        $u = User::factory()->create(); $u->forceFill(['role' => $role])->save();
        $this->app['auth']->forgetGuards();
        return $u->createToken('t')->plainTextToken;
    }

    public function test_full_crud_with_trash_restore_and_force_delete_for_every_entity(): void
    {
        foreach (Language::seedRows() as $l) Language::create($l);
        $t = $this->as('admin');
        $cases = [
            'emergency-resources' => [['country_code' => 'bd', 'name' => 'Fire Service', 'phone' => '102', 'category' => 'fire', 'source_name' => 'Gov', 'source_url' => 'https://fireservice.gov.bd'], ['name' => 'Fire Service and Civil Defence']],
            'incidents' => [['title' => 'Crash', 'category' => 'road_accident', 'severity' => 'high', 'lat' => 23.71234, 'lng' => 90.41234], ['severity' => 'critical']],
            'languages' => [['code' => 'xh', 'native_name' => 'isiXhosa', 'english_name' => 'Xhosa'], ['enabled' => false]],
            'users' => [['name' => 'New', 'email' => 'new@example.com', 'password' => 'password123'], ['name' => 'Renamed']],
        ];
        foreach ($cases as $entity => [$create, $update]) {
            $id = $this->withToken($t)->postJson("/api/v1/crud/$entity", $create)->assertCreated()->json('data.id');
            $this->withToken($t)->getJson("/api/v1/crud/$entity/$id")->assertOk();
            $this->withToken($t)->patchJson("/api/v1/crud/$entity/$id", $update)->assertOk()->assertJsonPath('data.'.array_key_first($update), array_values($update)[0]);
            $this->withToken($t)->getJson("/api/v1/crud/$entity?q=".urlencode((string) ($create['name'] ?? $create['title'] ?? $create['code']))."&per_page=5")->assertOk();
            $this->withToken($t)->deleteJson("/api/v1/crud/$entity/$id/force")->assertStatus(422); // must be in Trash first
            $this->withToken($t)->deleteJson("/api/v1/crud/$entity/$id")->assertOk();
            $this->withToken($t)->getJson("/api/v1/crud/$entity?trashed=only")->assertOk()->assertJsonPath('data.0.id', $id);
            $this->withToken($t)->postJson("/api/v1/crud/$entity/$id/restore")->assertOk()->assertJsonPath('data.deleted_at', null);
            $this->withToken($t)->deleteJson("/api/v1/crud/$entity/$id")->assertOk();
            $this->withToken($t)->deleteJson("/api/v1/crud/$entity/$id/force")->assertNoContent();
            $this->withToken($t)->getJson("/api/v1/crud/$entity/$id")->assertNotFound();
        }
    }

    public function test_crud_never_exposes_exact_location_or_accepts_role_and_respects_policy(): void
    {
        $admin = $this->as('admin');
        $r = $this->withToken($admin)->postJson('/api/v1/crud/incidents', ['title' => 'X', 'category' => 'fire', 'severity' => 'low', 'lat' => 23.712345, 'lng' => 90.412345])->assertCreated();
        $this->assertArrayNotHasKey('lat', $r->json('data'));
        $uid = $this->withToken($admin)->postJson('/api/v1/crud/users', ['name' => 'U', 'email' => 'u@example.com', 'password' => 'password123', 'role' => 'admin'])->json('data.id');
        $this->withToken($admin)->patchJson("/api/v1/crud/users/$uid", ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'citizen');

        $mod = $this->as('moderator');
        $this->withToken($mod)->getJson('/api/v1/crud/incidents')->assertOk();
        $this->withToken($mod)->getJson('/api/v1/crud/users')->assertForbidden();
        $cit = $this->as('citizen');
        $this->withToken($cit)->getJson('/api/v1/crud/incidents')->assertForbidden();
    }

    public function test_soft_deleted_incident_disappears_from_public_feed(): void
    {
        $admin = $this->as('admin');
        $id = $this->withToken($admin)->postJson('/api/v1/crud/incidents', ['title' => 'Gone', 'category' => 'fire', 'severity' => 'low', 'lat' => 23.7, 'lng' => 90.4])->json('data.id');
        $this->withToken($admin)->deleteJson("/api/v1/crud/incidents/$id")->assertOk();
        $this->assertSame(0, Incident::count());
        $this->assertSame(1, Incident::withTrashed()->count());
    }

    public function test_languages_api_english_default_hand_and_machine_translation_with_rtl(): void
    {
        foreach (Language::seedRows() as $l) Language::create($l);
        $this->getJson('/api/v1/languages')->assertOk()->assertJsonPath('default', 'en')->assertJsonFragment(['code' => 'ar', 'rtl' => true, 'native_name' => 'العربية']);
        $this->getJson('/api/v1/i18n/bn')->assertOk()->assertHeader('X-RL-Machine-Translated', '0')->assertJsonPath('feed', \App\Services\I18n\TranslationService::source('bn')['feed']);

        $this->app->bind(TextTranslator::class, fn () => new class implements TextTranslator {
            public int $calls = 0;
            public function translate(array $s, string $c, string $n): array { return array_map(fn ($v) => "[$c] $v", $s); }
            public function name(): string { return 'fake'; }
        });
        $r = $this->getJson('/api/v1/i18n/ar')->assertOk()->assertHeader('X-RL-Machine-Translated', '1')->assertHeader('X-RL-Missing', '0');
        $this->assertStringStartsWith('[ar] ', $r->json('feed'));
        $this->assertGreaterThan(100, \App\Models\UiTranslation::where('lang', 'ar')->count()); // cached
        $this->getJson('/api/v1/i18n/zz')->assertNotFound();
        $this->assertFalse(\App\Services\I18n\TranslationService::placeholdersMatch('{{n}} km', '{{x}} km'));
    }

    public function test_backend_ui_strings_match_frontend_source(): void
    {
        foreach (['en', 'bn'] as $c) {
            $front = base_path("../frontend/src/locales/$c.json");
            if (is_file($front)) $this->assertJsonFileEqualsJsonFile($front, resource_path("i18n/$c.json"), "Run: cp frontend/src/locales/$c.json backend/resources/i18n/");
        }
    }

    public function test_private_address_is_saved_but_never_public(): void
    {
        $u = \App\Models\User::factory()->create(['role' => 'citizen']);
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/incidents', ['title' => 'Water logging', 'category' => 'flood', 'severity' => 'moderate', 'lat' => 23.7808875, 'lng' => 90.4161712,
            'area_name' => 'Gulshan', 'address' => 'House 12, Road 5, Gulshan 1, Dhaka', 'consent_public' => true])->assertCreated();
        $i = \App\Models\Incident::latest('id')->first();
        $this->assertSame('House 12, Road 5, Gulshan 1, Dhaka', $i->address);
        $pub = $this->getJson('/api/v1/incidents/'.$i->public_id)->assertOk()->getContent();
        $this->assertStringNotContainsString('Road 5', $pub);
        $this->assertStringNotContainsString('23.7808875', $pub);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/crud/incidents/'.$i->public_id, ['lat' => 23.75, 'lng' => 90.39, 'address' => 'Dhanmondi 27'])
            ->assertOk()->assertJsonPath('data.address', 'Dhanmondi 27')->assertJsonMissingPath('data.lat');
    }
}
