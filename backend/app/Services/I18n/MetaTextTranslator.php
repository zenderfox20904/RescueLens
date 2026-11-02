<?php

namespace App\Services\I18n;

use App\Services\AI\MetaModelClient;

class MetaTextTranslator implements TextTranslator
{
    public function __construct(private MetaModelClient $client) {}

    public function translate(array $strings, string $targetCode, string $targetName): array
    {
        $res = $this->client->responses([
            'model' => config('rescuelens.meta.vision_model'),
            'instructions' => PromptTranslation::system($targetCode, $targetName),
            'input' => json_encode($strings, JSON_UNESCAPED_UNICODE),
        ]);
        return PromptTranslation::parse(MetaModelClient::outputText($res));
    }

    public function name(): string { return 'meta-model-api'; }
}
