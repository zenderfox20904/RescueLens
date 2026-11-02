<?php

namespace App\Services\Integrations;

use App\Models\Incident;
use App\Support\Present;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound partner webhooks (incident.created, incident.verified, incident.resolved).
 * Payload contains only public, approximate data. Signed with HMAC SHA256 in X-RescueLens-Signature.
 * Delivered after the HTTP response so user requests stay fast.
 */
class WebhookDispatcher
{
    public const EVENTS = ['incident.created', 'incident.verified', 'incident.resolved'];

    public function dispatch(string $event, Incident $i): void
    {
        $urls = array_filter(array_map('trim', explode(',', (string) config('rescuelens.webhooks.urls'))));
        $secret = (string) config('rescuelens.webhooks.secret');
        if (! $urls || ! $secret || ! in_array($event, self::EVENTS, true)) return;
        $body = json_encode(['event' => $event, 'sent_at' => now()->toIso8601String(), 'data' => Present::incident($i->loadMissing('user', 'media', 'analyses.regions'), null)], JSON_UNESCAPED_UNICODE);
        $sig = 'sha256='.hash_hmac('sha256', $body, $secret);
        dispatch(function () use ($urls, $body, $sig, $event) {
            foreach ($urls as $u) {
                try {
                    Http::timeout(5)->retry(2, 300, throw: false)->withHeaders(['X-RescueLens-Event' => $event, 'X-RescueLens-Signature' => $sig])
                        ->withBody($body, 'application/json')->post($u);
                } catch (\Throwable $e) {
                    Log::warning('webhook delivery failed', ['event' => $event, 'host' => parse_url($u, PHP_URL_HOST)]);
                }
            }
        })->afterResponse();
    }
}
