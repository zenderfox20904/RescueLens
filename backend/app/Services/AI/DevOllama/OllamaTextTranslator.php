<?php

namespace App\Services\AI\DevOllama;

use App\Services\I18n\PromptTranslation;
use App\Services\I18n\TextTranslator;
use Illuminate\Support\Facades\Http;

/** DEV ONLY. TEMPORARY. Machine translation through local Ollama. Delete with the DevOllama folder. */
class OllamaTextTranslator implements TextTranslator
{
    public function translate(array $strings, string $targetCode, string $targetName): array
    {
        $res = Http::timeout((int) config('rescuelens.dev_ollama.timeout'))->post(rtrim(config('rescuelens.dev_ollama.url'), '/').'/api/chat', [
            'model' => config('rescuelens.dev_ollama.model'), 'stream' => false, 'think' => false, 'format' => 'json',
            'options' => ['temperature' => 0.1],
            'messages' => [
                ['role' => 'system', 'content' => PromptTranslation::system($targetCode, $targetName)],
                ['role' => 'user', 'content' => json_encode($strings, JSON_UNESCAPED_UNICODE)],
            ],
        ]);
        return $res->successful() ? PromptTranslation::parse((string) $res->json('message.content')) : [];
    }

    public function name(): string { return 'ollama-dev:'.config('rescuelens.dev_ollama.model'); }
}
