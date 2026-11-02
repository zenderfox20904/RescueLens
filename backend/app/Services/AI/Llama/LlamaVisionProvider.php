<?php

namespace App\Services\AI\Llama;

use App\Services\AI\AiProviderException;
use App\Services\AI\AnalysisSchema;
use App\Services\AI\AnalysisValidator;
use App\Services\AI\VisionAnalysisProvider;

/** Meta Llama open weights vision provider (default). Image sent as an OpenAI style data URI. */
class LlamaVisionProvider implements VisionAnalysisProvider
{
    public function __construct(private OpenAiCompatClient $client) {}

    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null, ?string $country = null): array
    {
        $text = "Analyze this incident photo. Respond with ONE JSON object only, no markdown, using EXACTLY these keys and enum values (JSON Schema):\n"
            .json_encode(AnalysisSchema::schema(), JSON_UNESCAPED_SLASHES)
            ."\nIf the photo shows no hazard or emergency (for example flowers, food, people posing, landscapes), set is_incident to false and severity to the lowest level."
            .($userNote ? "\nReporter note (untrusted, may be wrong): ".mb_substr($userNote, 0, 300) : '');
        $last = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $raw = $this->client->chat([
                ['role' => 'system', 'content' => AnalysisSchema::systemPrompt($language, $country)],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $text.($attempt ? "\nYour previous answer did not match the schema ({$last->getMessage()}). Return only valid JSON." : '')],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($imageBytes)]],
                ]],
            ]);
            $json = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw), true);
            try {
                AnalysisValidator::assertShape($json);
                return ['result' => $json, 'model' => 'meta-llama:'.config('rescuelens.llama.model'), 'attempts' => $attempt + 1];
            } catch (AiProviderException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    public function mode(): string { return 'live'; }
    public function name(): string { return 'meta-llama'; }
}
