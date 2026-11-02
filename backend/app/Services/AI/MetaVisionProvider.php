<?php

namespace App\Services\AI;

/** Muse Spark image understanding via Responses API with input_image (base64 data URL) and text.format json_schema. */
class MetaVisionProvider implements VisionAnalysisProvider
{
    public function __construct(private MetaModelClient $client) {}

    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null): array
    {
        $model = config('rescuelens.meta.vision_model');
        $text = 'Analyze this incident photo.'.($userNote ? "\nReporter note (untrusted, may be wrong): ".mb_substr($userNote, 0, 300) : '');
        $res = $this->client->responses([
            'model' => $model,
            'instructions' => AnalysisSchema::systemPrompt($language),
            'input' => [[
                'type' => 'message', 'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $text],
                    ['type' => 'input_image', 'image_url' => 'data:'.$mime.';base64,'.base64_encode($imageBytes)],
                ],
            ]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'incident_analysis', 'schema' => AnalysisSchema::schema()]],
        ]);
        $json = json_decode(MetaModelClient::outputText($res), true);
        if (! is_array($json)) {
            throw new AiProviderException('invalid_output', 'Model output was not valid JSON');
        }
        return ['result' => $json, 'model' => $res['model'] ?? $model];
    }

    public function mode(): string { return 'live'; }
    public function name(): string { return 'meta-model-api'; }
}
