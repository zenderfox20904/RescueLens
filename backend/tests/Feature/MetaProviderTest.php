<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Provider selection and a mocked Meta Llama API round trip. No network calls. */
class MetaProviderTest extends TestCase
{
    private function rebind(array $cfg): void
    {
        config($cfg);
        (new \App\Providers\AppServiceProvider($this->app))->register();
    }

    public function test_meta_model_api_is_selected_when_key_is_set(): void
    {
        $this->rebind(['rescuelens.ai_provider' => 'meta', 'rescuelens.meta.api_key' => 'test_key']);
        $this->assertInstanceOf(\App\Services\AI\MetaVisionProvider::class, app(\App\Services\AI\VisionAnalysisProvider::class));
        $this->assertInstanceOf(\App\Services\AI\MetaSamProvider::class, app(\App\Services\AI\ImageSegmentationProvider::class));
    }

    public function test_without_key_the_demo_provider_is_used_and_labeled(): void
    {
        $this->rebind(['rescuelens.ai_provider' => 'meta', 'rescuelens.meta.api_key' => null]);
        $this->assertSame('demo', app(\App\Services\AI\VisionAnalysisProvider::class)->mode());
    }

    public function test_llama_api_analysis_is_parsed_from_a_mocked_response(): void
    {
        $this->rebind(['rescuelens.ai_provider' => 'llama', 'rescuelens.llama.api_key' => 'test_key', 'rescuelens.llama.base_url' => 'https://api.llama.com/compat/v1']);
        $body = \App\Services\AI\DemoFixtures::analysis('fire', 'en') + ['is_incident_scene' => true, 'summary_en' => 'x', 'suggested_title_en' => 'x', 'vehicles_involved' => [], 'people_at_risk' => 'possible', 'people_at_risk_reason' => 'x', 'severity_reason' => 'x'];
        Http::fake(['api.llama.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($body)]]]])]);
        $p = app(\App\Services\AI\VisionAnalysisProvider::class);
        $this->assertSame('meta-llama', $p->name());
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $out = $p->analyze($png, 'image/png', 'en', null);
        $this->assertIsArray($out['result']);
        $this->assertNotEmpty($out['result']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.llama.com/compat/v1') && $r->hasHeader('Authorization'));
    }
}
