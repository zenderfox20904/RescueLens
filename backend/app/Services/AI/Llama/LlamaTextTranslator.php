<?php

namespace App\Services\AI\Llama;

use App\Services\I18n\TextTranslator;

/** Translates UI or report strings with Meta Llama. Returns the input unchanged on any failure. */
class LlamaTextTranslator implements TextTranslator
{
    public function __construct(private OpenAiCompatClient $client) {}

    public function translate(array $strings, string $targetCode, string $targetName): array
    {
        if (! $strings) return [];
        try {
            $raw = $this->client->chat([
                ['role' => 'system', 'content' => "You translate short app strings into {$targetName} ({$targetCode}). Keep placeholders like {n} and :name unchanged. Reply with a JSON object mapping each input key to its translation."],
                ['role' => 'user', 'content' => json_encode($strings, JSON_UNESCAPED_UNICODE)],
            ], true, 0.1);
            $out = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw), true);
            if (! is_array($out)) return $strings;
            return array_map(fn ($k) => is_string($out[$k] ?? null) && $out[$k] !== '' ? $out[$k] : $strings[$k], array_combine(array_keys($strings), array_keys($strings)));
        } catch (\Throwable) {
            return $strings;
        }
    }

    public function name(): string { return 'meta-llama:'.config('rescuelens.llama.model'); }
}
