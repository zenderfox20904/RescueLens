<?php

namespace App\Services\AI\Llama;

use App\Services\AI\AiProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal OpenAI compatible chat client for Meta Llama open weights models.
 * Works with a local Ollama server (default, http://localhost:11434/v1) or any hosted
 * OpenAI compatible endpoint serving Llama (Baseten, Modal, Fireworks) by setting
 * LLAMA_BASE_URL, LLAMA_API_KEY and LLAMA_MODEL. The key is never logged.
 */
class OpenAiCompatClient
{
    public function chat(array $messages, bool $json = true, ?float $temperature = 0.2): string
    {
        $cfg = config('rescuelens.llama');
        $req = Http::timeout((int) $cfg['timeout'])->acceptJson();
        if (filled($cfg['api_key'])) $req = $req->withToken($cfg['api_key']);
        $body = ['model' => $cfg['model'], 'messages' => $messages, 'stream' => false];
        if ($temperature !== null) $body['temperature'] = $temperature;
        if ($json) $body['response_format'] = ['type' => 'json_object'];
        try {
            $res = $req->post(rtrim($cfg['base_url'], '/').'/chat/completions', $body);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new AiProviderException('provider_unavailable', 'Llama endpoint is not reachable', true);
        }
        if (! $res->successful()) throw new AiProviderException('provider_unavailable', 'Llama endpoint HTTP '.$res->status(), $res->status() >= 500 || $res->status() === 429);
        return trim((string) $res->json('choices.0.message.content'));
    }

    public static function label(): string
    {
        return (string) (config('rescuelens.llama.label') ?: config('rescuelens.llama.model'));
    }
}
