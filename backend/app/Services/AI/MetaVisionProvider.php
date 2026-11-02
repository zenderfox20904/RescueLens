<?php

namespace App\Services\AI;

/**
 * Muse Spark image understanding via the Responses API with input_image (base64 data URL),
 * text.format json_schema (structured output), reasoning.effort and a low temperature.
 * Docs: https://dev.meta.ai/docs/image-understanding, https://dev.meta.ai/docs/structured-output, https://dev.meta.ai/docs/reasoning
 * If the output fails server validation it retries exactly once with a correction note.
 */
class MetaVisionProvider implements VisionAnalysisProvider
{
    public function __construct(private MetaModelClient $client) {}

    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null, ?string $country = null): array
    {
        $model = config('rescuelens.meta.vision_model');
        $text = 'Analyze this incident photo . Respond in the JSON schema.'.($userNote ? "\nReporter note (untrusted, may be wrong): ".mb_substr($userNote, 0, 300) : '');
        $last = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $payload = [
                'model' => $model,
                'instructions' => AnalysisSchema::systemPrompt($language, $country),
                'input' => [[
                    'type' => 'message', 'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $text.($attempt ? "\nYour previous answer did not match the schema ({$last->getMessage()}). Return only valid JSON for the schema." : '')],
                        ['type' => 'input_image', 'image_url' => 'data:'.$mime.';base64,'.base64_encode($imageBytes)],
                    ],
                ]],
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'incident_analysis', 'strict' => true, 'schema' => AnalysisSchema::schema()]],
                'reasoning' => ['effort' => config('rescuelens.meta.reasoning_effort', 'high')],
            ];
            $temp = config('rescuelens.meta.temperature');
            if ($temp !== null && $temp !== '') $payload['temperature'] = (float) $temp;
            $res = $this->client->responses($payload);
            $json = json_decode(MetaModelClient::outputText($res), true);
            try {
                AnalysisValidator::assertShape($json);
                return ['result' => $json, 'model' => $res['model'] ?? $model, 'attempts' => $attempt + 1];
            } catch (AiProviderException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    public function mode(): string { return 'live'; }
    public function name(): string { return 'meta-model-api'; }
}
