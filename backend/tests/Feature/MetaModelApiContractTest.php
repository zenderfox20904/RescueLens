<?php

namespace Tests\Feature;

use App\Services\AI\AiProviderException;
use App\Services\AI\DemoFixtures;
use App\Services\AI\MetaModelClient;
use App\Services\AI\MetaVisionProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contract test against the documented Meta Model API shapes (mocked, no network).
 * Sources: https://dev.meta.ai/docs/api-reference, /docs/quickstart (Responses object),
 * /docs/image-understanding (input_image), /docs/error-handling (error envelope), /docs/pricing-rate-limits.
 */
class MetaModelApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['rescuelens.meta.api_key' => 'LLM_1_test', 'rescuelens.meta.base_url' => 'https://api.meta.ai/v1',
            'rescuelens.meta.vision_model' => 'muse-spark-1.3', 'rescuelens.meta.max_retries' => 2,
            'rescuelens.meta.max_retry_after' => 0, 'rescuelens.meta.temperature' => '']);
    }

    /** Documented Responses API success object (quickstart example) carrying our JSON output. */
    private function responseBody(string $text): array
    {
        return ['id' => 'resp_abc123', 'object' => 'response', 'created_at' => 1792177394, 'status' => 'completed',
            'model' => 'muse-spark-1.3', 'reasoning' => ['effort' => 'high'],
            'output' => [
                ['id' => 'rs_abc123', 'type' => 'reasoning', 'summary' => [], 'status' => 'completed'],
                ['id' => 'msg_abc123', 'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
                    'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]],
            ],
            'usage' => ['input_tokens' => 15, 'input_tokens_details' => ['cached_tokens' => 0], 'output_tokens' => 258,
                'output_tokens_details' => ['reasoning_tokens' => 241], 'total_tokens' => 273]];
    }

    private function err(string $type, ?string $code, string $msg = 'x'): array
    {
        return ['error' => ['message' => $msg, 'type' => $type, 'param' => null, 'code' => $code]];
    }

    public function test_vision_request_matches_documented_shape_and_parses_output(): void
    {
        $valid = DemoFixtures::analysis(DemoFixtures::pick(null, 'abcd'), 'en');
        Http::fake(['api.meta.ai/*' => Http::response($this->responseBody(json_encode($valid)), 200,
            ['x-ratelimit-remaining-requests' => '2999', 'x-ratelimit-remaining-tokens' => '3999000'])]);

        $out = (new MetaVisionProvider(new MetaModelClient))->analyze('img', 'image/jpeg', 'en', null);

        $this->assertSame('muse-spark-1.3', $out['model']);
        $this->assertSame(1, $out['attempts']);
        Http::assertSent(function ($r) {
            $c = $r['input'][0]['content'];
            return $r->url() === 'https://api.meta.ai/v1/responses'
                && $r->hasHeader('Authorization', 'Bearer LLM_1_test')
                && $r['model'] === 'muse-spark-1.3'
                && $r['input'][0]['role'] === 'user'
                && $c[0]['type'] === 'input_text'
                && $c[1]['type'] === 'input_image' && str_starts_with($c[1]['image_url'], 'data:image/jpeg;base64,')
                && $r['text']['format']['type'] === 'json_schema'
                && ! isset($r['temperature']);
        });
        $this->assertSame('2999', Cache::get('meta_api_ratelimit')['remaining_requests']);
    }

    public function test_model_name_comes_from_env_config(): void
    {
        config(['rescuelens.meta.vision_model' => 'muse-spark-1.3-contributor']);
        $valid = DemoFixtures::analysis(DemoFixtures::pick(null, 'abcd'), 'en');
        Http::fake(['api.meta.ai/*' => Http::response($this->responseBody(json_encode($valid)))]);
        (new MetaVisionProvider(new MetaModelClient))->analyze('img', 'image/png', 'en', null);
        Http::assertSent(fn ($r) => $r['model'] === 'muse-spark-1.3-contributor');
    }

    /** @return array<string, array{int, array, string, int}> */
    public static function errors(): array
    {
        return [
            '401 invalid_api_key' => [401, ['authentication_error', 'invalid_api_key'], 'auth_failed', 1],
            '402 billing' => [402, ['billing_error', 'billing_not_configured'], 'billing', 1],
            '404 model_not_found' => [404, ['invalid_request_error', 'model_not_found'], 'model_not_found', 1],
            '400 invalid request' => [400, ['invalid_request_error', null], 'bad_request', 1],
            '504 gateway_timeout' => [504, ['server_error', 'gateway_timeout'], 'gateway_timeout', 1],
            '429 retried' => [429, ['rate_limit_error', 'rate_limit_exceeded'], 'rate_limited', 3],
            '503 retried' => [503, ['server_error', 'service_overloaded'], 'provider_unavailable', 3],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errors')]
    public function test_documented_errors_map_and_retry_only_transient(int $status, array $e, string $code, int $calls): void
    {
        Http::fake(['api.meta.ai/*' => Http::response($this->err($e[0], $e[1]), $status, ['Retry-After' => '1'])]);
        try {
            (new MetaModelClient)->responses(['model' => 'muse-spark-1.3', 'input' => 'hi']);
            $this->fail('expected exception');
        } catch (AiProviderException $x) {
            $this->assertSame($code, $x->errorCode);
        }
        Http::assertSentCount($calls);
    }

    public function test_transient_error_then_success(): void
    {
        Http::fake(['api.meta.ai/*' => Http::sequence()
            ->push($this->err('rate_limit_error', 'rate_limit_exceeded'), 429)
            ->push($this->responseBody('ok'))]);
        $res = (new MetaModelClient)->responses(['model' => 'muse-spark-1.3', 'input' => 'hi']);
        $this->assertSame('ok', MetaModelClient::outputText($res));
    }

    public function test_missing_key_never_calls_network(): void
    {
        config(['rescuelens.meta.api_key' => null]);
        Http::fake();
        $this->expectException(AiProviderException::class);
        try { (new MetaModelClient)->responses(['model' => 'm', 'input' => 'x']); } finally { Http::assertNothingSent(); }
    }
}
