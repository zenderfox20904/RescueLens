<?php

namespace App\Services\AI\DevOllama;

use App\Services\AI\AiProviderException;
use App\Services\AI\AnalysisSchema;
use App\Services\AI\AnalysisValidator;
use App\Services\AI\VisionAnalysisProvider;
use Illuminate\Support\Facades\Http;

/**
 * DEV ONLY. TEMPORARY. Local Ollama provider used while Meta Model API access is pending.
 * Selected only by AI_PROVIDER=ollama and refused when APP_ENV=production.
 * Delete the whole app/Services/AI/DevOllama folder and its binding in AppServiceProvider before production.
 * Uses Ollama /api/chat with a JSON schema in `format` and the image as base64 in `images`.
 */
class OllamaVisionProvider implements VisionAnalysisProvider
{
    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null, ?string $country = null): array
    {
        $model = config('rescuelens.dev_ollama.model');
        // Cloud models served through Ollama may ignore `format`, so the schema is also stated in the prompt.
        $text = "Analyze this incident photo. Respond with ONE JSON object only, no markdown, using EXACTLY these keys and enum values (JSON Schema):\n"
            .json_encode(AnalysisSchema::schema(), JSON_UNESCAPED_SLASHES).($userNote ? "\nReporter note (untrusted, may be wrong): ".mb_substr($userNote, 0, 300) : '');
        $last = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $res = Http::timeout((int) config('rescuelens.dev_ollama.timeout'))->post(rtrim(config('rescuelens.dev_ollama.url'), '/').'/api/chat', [
                    'model' => $model, 'stream' => false, 'think' => false,
                    'format' => AnalysisSchema::schema(),
                    'options' => ['temperature' => 0.2],
                    'messages' => [
                        ['role' => 'system', 'content' => AnalysisSchema::systemPrompt($language, $country)],
                        ['role' => 'user', 'content' => $text.($attempt ? "\nYour previous answer did not match the schema ({$last->getMessage()}). Return only valid JSON." : ''),
                            'images' => [base64_encode($imageBytes)]],
                    ],
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException) {
                throw new AiProviderException('provider_unavailable', 'Ollama is not reachable', true);
            }
            if (! $res->successful()) throw new AiProviderException('provider_unavailable', 'Ollama HTTP '.$res->status(), true);
            $raw = trim((string) $res->json('message.content'));
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw);
            $json = json_decode($raw, true);
            try {
                AnalysisValidator::assertShape($json);
                return ['result' => $json, 'model' => 'ollama-dev:'.$model, 'attempts' => $attempt + 1];
            } catch (AiProviderException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    public function mode(): string { return 'dev'; }
    public function name(): string { return 'ollama-dev'; }
}
