<?php

namespace Tests\Feature;

use App\Services\AI\Llama\LlamaVisionProvider;
use App\Services\AI\Llama\OpenAiCompatClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlamaProviderTest extends TestCase
{
    public function test_llama_provider_sends_openai_compatible_request_and_validates(): void
    {
        config(['rescuelens.llama.base_url' => 'https://llama.example/v1', 'rescuelens.llama.api_key' => 'k', 'rescuelens.llama.model' => 'llama3.2-vision']);
        $valid = \App\Services\AI\DemoFixtures::analysis(\App\Services\AI\DemoFixtures::pick(null, 'abcd'), 'en');
        Http::fake(['llama.example/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'not json']]]])
            ->push(['choices' => [['message' => ['content' => json_encode($valid)]]]])]);
        $p = new LlamaVisionProvider(new OpenAiCompatClient);
        $out = $p->analyze('img', 'image/jpeg', 'en', null);
        $this->assertSame('meta-llama:llama3.2-vision', $out['model']);
        $this->assertSame(2, $out['attempts']);
        Http::assertSent(fn ($r) => $r->url() === 'https://llama.example/v1/chat/completions' && $r->hasHeader('Authorization', 'Bearer k')
            && str_starts_with($r['messages'][1]['content'][1]['image_url']['url'], 'data:image/jpeg;base64,'));
        $this->assertSame('live', $p->mode());
    }
}
