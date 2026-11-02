<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for Meta Model API (https://api.meta.ai/v1), Responses API.
 * Docs: https://dev.meta.ai/docs, https://dev.meta.ai/docs/image-understanding,
 * https://dev.meta.ai/docs/structured-output, https://dev.meta.ai/docs/sam/segmenting
 */
class MetaModelClient
{
    public function configured(): bool
    {
        return filled(config('rescuelens.meta.api_key'));
    }

    public function responses(array $payload): array
    {
        if (! $this->configured()) {
            throw new AiProviderException('not_configured', 'Meta Model API key is not set');
        }
        $this->enforceBudget();
        $breakerKey = 'meta_api_breaker';
        if (Cache::get($breakerKey, 0) >= 5) {
            throw new AiProviderException('provider_unavailable', 'Circuit open after repeated provider failures', true);
        }

        $attempts = max(1, config('rescuelens.meta.max_retries') + 1);
        $last = null;
        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                usleep((int) ((2 ** $i) * 250_000 + random_int(0, 250_000))); // bounded backoff with jitter
            }
            try {
                $res = Http::withToken(config('rescuelens.meta.api_key'))
                    ->acceptJson()
                    ->timeout(config('rescuelens.meta.timeout'))
                    ->post(config('rescuelens.meta.base_url').'/responses', $payload);
            } catch (ConnectionException $e) {
                $last = new AiProviderException('provider_unavailable', 'Connection to Meta Model API failed', true);
                continue;
            }
            $s = $res->status();
            if ($res->successful()) {
                Cache::forget($breakerKey);
                Cache::put('meta_api_ratelimit', ['remaining_requests' => $res->header('x-ratelimit-remaining-requests'), 'remaining_tokens' => $res->header('x-ratelimit-remaining-tokens')], 300);
                return $res->json() ?? [];
            }
            // Error envelope per https://dev.meta.ai/docs/error-handling: {error:{message,type,param,code}}
            $code = (string) ($res->json('error.code') ?? '');
            $last = match (true) {
                $s === 401 => new AiProviderException('auth_failed', 'Meta Model API rejected the key'),
                $s === 402 => new AiProviderException('billing', 'Meta Model API billing issue'),
                $s === 403 => new AiProviderException('forbidden', 'Key lacks access to this model'),
                $s === 404 => new AiProviderException('model_not_found', 'Model not available for this account'),
                $s === 413 => new AiProviderException('payload_too_large', 'Request body too large'),
                $s === 429 => new AiProviderException('rate_limited', 'Meta Model API rate limit', true),
                $s === 504 => new AiProviderException('gateway_timeout', 'Meta Model API timed out'),
                $s >= 500 => new AiProviderException('provider_unavailable', 'Meta Model API error '.$s.($code ? ' '.$code : ''), true),
                default => new AiProviderException('bad_request', 'Meta Model API returned '.$s),
            };
            if (! $last->retryable) {
                break;
            }
            // Honor Retry-After (seconds) when present, capped so a request never blocks for long.
            $ra = (int) $res->header('Retry-After');
            if ($ra > 0 && $i + 1 < $attempts) {
                usleep(min($ra, (int) config('rescuelens.meta.max_retry_after', 5)) * 1_000_000);
            }
        }
        Cache::increment($breakerKey);
        Cache::put($breakerKey, Cache::get($breakerKey, 1), 300);
        throw $last;
    }

    /** Concatenate output_text parts from a Responses API result. */
    public static function outputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }
        $out = '';
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $out .= $part['text'] ?? '';
                }
            }
        }
        return $out;
    }

    private function enforceBudget(): void
    {
        $key = 'meta_api_calls_'.now()->toDateString();
        $n = Cache::increment($key);
        if ($n === 1) {
            Cache::put($key, 1, now()->endOfDay());
        }
        if ($n > config('rescuelens.meta.daily_budget')) {
            throw new AiProviderException('budget_exceeded', 'Daily AI request budget reached');
        }
    }
}
